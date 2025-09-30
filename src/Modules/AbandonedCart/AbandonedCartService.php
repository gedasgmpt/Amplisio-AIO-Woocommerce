<?php

namespace Amplisio\AIO\Modules\AbandonedCart;

use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use wpdb;

class AbandonedCartService
{
    private string $table;

    public function __construct(private wpdb $wpdb)
    {
        $this->table = $wpdb->prefix . 'amplisio_carts';
    }

    public function get_table_name(): string
    {
        return $this->table;
    }

    public function maybe_create_table(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_key varchar(64) NOT NULL,
            email varchar(190) NULL,
            email_hash char(64) NULL,
            first_name varchar(190) NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            subtotal decimal(18,6) NOT NULL DEFAULT 0,
            items longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            abandoned_at datetime NULL,
            recovered_order_id bigint(20) unsigned NULL,
            recovered_total decimal(18,6) NOT NULL DEFAULT 0,
            recovered_at datetime NULL,
            expires_at datetime NULL,
            consent tinyint(1) NOT NULL DEFAULT 0,
            restore_token char(64) NOT NULL,
            coupon_code varchar(40) NULL,
            coupon_expires_at datetime NULL,
            sequence_log longtext NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cart_key (cart_key),
            KEY status (status),
            KEY updated_at (updated_at),
            KEY email (email(64))
        ) $charset;";

        dbDelta($sql);
    }

    /**
     * @param array{
     *     email?: string|null,
     *     consent?: bool,
     *     first_name?: string|null,
     *     items?: array<int, array<string, mixed>>,
     *     subtotal?: float,
     *     expires_at?: string|null
     * } $data
     */
    public function record_cart(string $cart_key, array $data): void
    {
        $now      = current_time('mysql', true);
        $items    = $this->prepare_items($data['items'] ?? []);
        $subtotal = isset($data['subtotal']) ? (float) $data['subtotal'] : 0.0;
        $email    = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
        $first    = isset($data['first_name']) ? sanitize_text_field((string) $data['first_name']) : '';
        $consent  = (bool) ($data['consent'] ?? false);
        $expires  = $data['expires_at'] ?? null;

        $stored_email = $consent ? $email : null;
        $email_hash   = $email ? $this->hash_email($email) : null;

        $existing = $this->get_cart_by_key($cart_key);

        $payload = [
            'email'         => $stored_email,
            'email_hash'    => $email_hash,
            'first_name'    => $first,
            'items'         => $items,
            'subtotal'      => $subtotal,
            'updated_at'    => $now,
            'expires_at'    => $expires ? gmdate('Y-m-d H:i:s', strtotime($expires)) : null,
            'consent'       => $consent ? 1 : 0,
        ];

        if (null === $existing) {
            $insert_payload = array_merge(
                [
                    'cart_key'      => $cart_key,
                    'status'        => 'active',
                    'created_at'    => $now,
                    'restore_token' => wp_generate_password(32, false),
                    'sequence_log'  => wp_json_encode([]),
                ],
                $payload
            );

            $this->wpdb->insert(
                $this->table,
                $insert_payload,
                $this->formats_for($insert_payload)
            );

            return;
        }

        $status = $existing['status'];
        if ('abandoned' === $status || 'expired' === $status) {
            $payload['status']       = 'active';
            $payload['abandoned_at'] = null;
        }

        $this->wpdb->update(
            $this->table,
            $payload,
            ['id' => $existing['id']],
            $this->formats_for($payload),
            ['%d']
        );
    }

    public function get_cart_by_key(string $cart_key): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE cart_key = %s", $cart_key),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function get_cart(int $id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * @param callable|null $scheduler Receives the cart row array when a cart becomes abandoned.
     */
    public function mark_carts_abandoned(int $threshold_minutes, ?callable $scheduler = null): void
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($threshold_minutes * MINUTE_IN_SECONDS));
        $rows   = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE status = 'active' AND updated_at <= %s", $cutoff),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $abandoned_at = current_time('mysql', true);
            $this->wpdb->update(
                $this->table,
                [
                    'status'       => 'abandoned',
                    'abandoned_at' => $abandoned_at,
                    'updated_at'   => $abandoned_at,
                ],
                ['id' => $row['id']],
                ['%s', '%s', '%s'],
                ['%d']
            );

            if (null !== $scheduler) {
                $row['abandoned_at'] = $abandoned_at;
                $scheduler($row);
            }
        }
    }

    public function mark_recovered(int $cart_id, int $order_id, float $total, string $email, string $first_name = ''): void
    {
        $now   = current_time('mysql', true);
        $email = sanitize_email($email);
        $first = sanitize_text_field($first_name);

        $this->wpdb->update(
            $this->table,
            [
                'status'            => 'recovered',
                'recovered_order_id'=> $order_id,
                'recovered_total'   => $total,
                'recovered_at'      => $now,
                'updated_at'        => $now,
                'email'             => $email,
                'email_hash'        => $email ? $this->hash_email($email) : null,
                'first_name'        => $first,
                'consent'           => 1,
            ],
            ['id' => $cart_id],
            ['%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%d'],
            ['%d']
        );

        $this->add_sequence_recovery_marker($cart_id);
    }

    public function expire_abandoned_carts(int $days): void
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET status = 'expired', updated_at = %s WHERE status = 'abandoned' AND abandoned_at <= %s",
                current_time('mysql', true),
                $cutoff
            )
        );
    }

    public function issue_coupon_for_cart(int $cart_id, string $email, array $config): ?array
    {
        $cart = $this->get_cart($cart_id);
        if ( ! $cart ) {
            throw new RuntimeException('Cart not found.');
        }

        if ( ! empty($cart['coupon_code']) ) {
            return [
                'code'       => $cart['coupon_code'],
                'expires_at' => $cart['coupon_expires_at'],
            ];
        }

        $email = sanitize_email($email);
        if ( ! $email ) {
            return null;
        }

        $coupon = apply_filters('amplisio_aio_generate_coupon', null, $cart_id, $email, $config);
        if ( is_array($coupon) && ! empty($coupon['code']) ) {
            $this->store_coupon($cart_id, $coupon['code'], $coupon['expires_at'] ?? null);
            return [
                'code'       => $coupon['code'],
                'expires_at' => $coupon['expires_at'] ?? null,
            ];
        }

        if ( class_exists('WC_Coupon') ) {
            $code   = strtolower(wp_generate_password(10, false));
            $coupon = new \WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_amount((float) ($config['couponAmount'] ?? 0));
            $coupon->set_discount_type($config['couponType'] ?? 'percent');
            $coupon->set_email_restrictions([$email]);

            if ( ! empty($config['couponExpiryDays']) ) {
                $date = (new DateTimeImmutable('now', wp_timezone()))->add(new DateInterval('P' . (int) $config['couponExpiryDays'] . 'D'));
                $coupon->set_date_expires($date);
                $expires = $date->format('Y-m-d H:i:s');
            } else {
                $expires = null;
            }

            $coupon->save();

            $this->store_coupon($cart_id, $code, $expires);

            return [
                'code'       => $code,
                'expires_at' => $expires,
            ];
        }

        return null;
    }

    public function log_sequence_event(int $cart_id, array $event): void
    {
        $cart = $this->get_cart($cart_id);
        if ( ! $cart ) {
            return;
        }

        $log = [];
        if ( ! empty($cart['sequence_log']) ) {
            $decoded = json_decode((string) $cart['sequence_log'], true);
            if ( is_array($decoded) ) {
                $log = $decoded;
            }
        }

        $event['timestamp'] = current_time('mysql', true);
        $log[]              = $event;

        $data = [
            'sequence_log' => wp_json_encode($log),
            'updated_at'   => current_time('mysql', true),
        ];

        $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $cart_id],
            $this->formats_for($data),
            ['%d']
        );
    }

    public function get_stats(): array
    {
        $totals = $this->wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$this->table} GROUP BY status", ARRAY_A);
        $counts = [
            'active'    => 0,
            'abandoned' => 0,
            'recovered' => 0,
            'expired'   => 0,
        ];

        foreach ($totals as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $recovered_amount = (float) $this->wpdb->get_var("SELECT SUM(recovered_total) FROM {$this->table} WHERE status = 'recovered'");

        return [
            'counts'   => $counts,
            'revenue'  => $recovered_amount,
        ];
    }

    public function get_recent_recoveries(int $limit = 10): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT recovered_order_id, recovered_total, recovered_at, email FROM {$this->table} WHERE status = 'recovered' ORDER BY recovered_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    public function get_top_products(int $limit = 5): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT items FROM {$this->table} WHERE status = 'recovered' ORDER BY recovered_at DESC LIMIT %d",
                $limit * 5
            ),
            ARRAY_A
        );

        $products = [];

        foreach ($rows as $row) {
            $items = json_decode((string) $row['items'], true);
            if ( ! is_array($items) ) {
                continue;
            }

            foreach ($items as $item) {
                if ( empty($item['product_id']) ) {
                    continue;
                }

                $id = (int) $item['product_id'];
                if ( ! isset($products[$id]) ) {
                    $products[$id] = [
                        'product_id' => $id,
                        'name'       => sanitize_text_field($item['name'] ?? ''),
                        'quantity'   => 0,
                    ];
                }

                $products[$id]['quantity'] += (int) ($item['quantity'] ?? 0);
            }
        }

        usort($products, static fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

        return array_slice(array_values($products), 0, $limit);
    }

    public function get_sequence_performance(array $sequences): array
    {
        $stats = [];
        foreach ($sequences as $sequence) {
            $stats[$sequence['id']] = [
                'id'        => $sequence['id'],
                'name'      => $sequence['name'] ?? $sequence['id'],
                'sent'      => 0,
                'recovered' => 0,
            ];
        }

        $rows = $this->wpdb->get_results("SELECT sequence_log FROM {$this->table} WHERE sequence_log IS NOT NULL", ARRAY_A);
        foreach ($rows as $row) {
            $events = json_decode((string) $row['sequence_log'], true);
            if ( ! is_array($events) ) {
                continue;
            }

            foreach ($events as $event) {
                if ( empty($event['sequence_id']) || empty($stats[$event['sequence_id']]) ) {
                    continue;
                }

                if ( ('sent' === ($event['type'] ?? '')) ) {
                    $stats[$event['sequence_id']]['sent']++;
                }

                if ( ('recovered' === ($event['type'] ?? '')) ) {
                    $stats[$event['sequence_id']]['recovered']++;
                }
            }
        }

        return array_values($stats);
    }

    public function add_sequence_recovery_marker(int $cart_id): void
    {
        $cart = $this->get_cart($cart_id);
        if ( ! $cart ) {
            return;
        }

        $log = [];
        if ( ! empty($cart['sequence_log']) ) {
            $decoded = json_decode((string) $cart['sequence_log'], true);
            if ( is_array($decoded) ) {
                $log = $decoded;
            }
        }

        $last_sequence = null;
        for ($i = count($log) - 1; $i >= 0; $i--) {
            if (('sent' === ($log[$i]['type'] ?? '')) && ! empty($log[$i]['sequence_id'])) {
                $last_sequence = $log[$i]['sequence_id'];
                break;
            }
        }

        if (null === $last_sequence) {
            return;
        }

        $log[] = [
            'type'        => 'recovered',
            'sequence_id' => $last_sequence,
            'timestamp'   => current_time('mysql', true),
        ];

        $data = [
            'sequence_log' => wp_json_encode($log),
            'updated_at'   => current_time('mysql', true),
        ];

        $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $cart_id],
            $this->formats_for($data),
            ['%d']
        );
    }

    public function get_restore_payload(string $cart_key, string $token): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE cart_key = %s AND restore_token = %s",
                $cart_key,
                $token
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $items = json_decode((string) $row['items'], true);
        if ( ! is_array($items) ) {
            $items = [];
        }

        return [
            'items'       => $items,
            'coupon_code' => $row['coupon_code'] ?? null,
        ];
    }

    public function set_consent(int $cart_id, string $email, ?string $first_name = null): void
    {
        $email = sanitize_email($email);
        $data  = [
            'email'      => $email,
            'email_hash' => $email ? $this->hash_email($email) : null,
            'consent'    => $email ? 1 : 0,
        ];

        if (null !== $first_name) {
            $data['first_name'] = sanitize_text_field($first_name);
        }

        $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $cart_id],
            $this->formats_for($data),
            ['%d']
        );
    }

    public function find_carts_for_email(string $email): array
    {
        $email = sanitize_email($email);
        if ( ! $email ) {
            return [];
        }

        $hash = $this->hash_email($email);

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE email = %s OR email_hash = %s ORDER BY updated_at DESC",
                $email,
                $hash
            ),
            ARRAY_A
        );
    }

    public function erase_carts_for_email(string $email): int
    {
        $email = sanitize_email($email);
        if ( ! $email ) {
            return 0;
        }

        $hash = $this->hash_email($email);

        return (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE email = %s OR email_hash = %s",
                $email,
                $hash
            )
        );
    }

    private function store_coupon(int $cart_id, string $code, ?string $expires): void
    {
        $data = [
            'coupon_code'       => sanitize_text_field($code),
            'coupon_expires_at' => $expires ? gmdate('Y-m-d H:i:s', strtotime($expires)) : null,
            'updated_at'        => current_time('mysql', true),
        ];

        $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $cart_id],
            $this->formats_for($data),
            ['%d']
        );
    }

    private function prepare_items(array $items): string
    {
        $normalized = [];
        foreach ($items as $item) {
            $normalized[] = [
                'product_id'   => isset($item['product_id']) ? (int) $item['product_id'] : 0,
                'variation_id' => isset($item['variation_id']) ? (int) $item['variation_id'] : 0,
                'quantity'     => isset($item['quantity']) ? (int) $item['quantity'] : 0,
                'name'         => sanitize_text_field($item['name'] ?? ''),
            ];
        }

        return wp_json_encode($normalized);
    }

    private function hash_email(string $email): string
    {
        return hash_hmac('sha256', strtolower($email), wp_salt('amplisio_aio'));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function formats_for(array $data): array
    {
        $map = [];

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'subtotal':
                case 'recovered_total':
                    $map[] = '%f';
                    break;
                case 'recovered_order_id':
                    $map[] = '%d';
                    break;
                case 'consent':
                    $map[] = '%d';
                    break;
                default:
                    $map[] = '%s';
            }
        }

        return $map;
    }
}
