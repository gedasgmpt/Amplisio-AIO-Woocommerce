<?php

namespace Amplisio\AIO\Modules\RecoveredCarts;

use wpdb;

class RecoveredCartsService
{
    private string $table;

    public function __construct(private wpdb $wpdb)
    {
        $this->table = $wpdb->prefix . 'amplisio_recovered_carts';
    }

    public function maybe_create_table(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            email varchar(190) NOT NULL,
            recovered_amount decimal(18,6) NOT NULL DEFAULT 0,
            recovered_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'completed',
            PRIMARY KEY  (id),
            KEY status (status),
            KEY recovered_at (recovered_at)
        ) $charset;";

        dbDelta($sql);
    }

    public function record(int $order_id, string $email, float $amount): void
    {
        $this->wpdb->insert(
            $this->table,
            [
                'order_id'        => $order_id,
                'email'           => sanitize_email($email),
                'recovered_amount'=> $amount,
                'recovered_at'    => current_time('mysql'),
                'status'          => 'completed',
            ],
            ['%d', '%s', '%f', '%s', '%s']
        );
    }

    public function get_summary(): array
    {
        $total = (float) $this->wpdb->get_var("SELECT SUM(recovered_amount) FROM {$this->table} WHERE status = 'completed'");
        $count = (int) $this->wpdb->get_var("SELECT COUNT(id) FROM {$this->table} WHERE status = 'completed'");

        return [
            'count'  => $count,
            'amount' => $total,
        ];
    }

    public function cleanup(): void
    {
        $threshold = gmdate('Y-m-d H:i:s', strtotime('-180 days'));
        $this->wpdb->query(
            $this->wpdb->prepare("DELETE FROM {$this->table} WHERE recovered_at < %s", $threshold)
        );
    }
}
