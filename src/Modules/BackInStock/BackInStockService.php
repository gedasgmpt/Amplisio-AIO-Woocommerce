<?php

namespace Amplisio\AIO\Modules\BackInStock;

use wpdb;

class BackInStockService
{
    private string $table;

    public function __construct(private wpdb $wpdb)
    {
        $this->table = $wpdb->prefix . 'amplisio_back_in_stock';
    }

    public function maybe_create_table(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            email varchar(190) NOT NULL,
            created_at datetime NOT NULL,
            notified_at datetime NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY notified_at (notified_at)
        ) $charset;";

        dbDelta($sql);
    }

    public function record_signup(int $product_id, string $email): void
    {
        $this->wpdb->insert(
            $this->table,
            [
                'product_id' => $product_id,
                'email'      => sanitize_email($email),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );
    }

    public function get_summary(): array
    {
        $total = (int) $this->wpdb->get_var("SELECT COUNT(id) FROM {$this->table}");
        $pending = (int) $this->wpdb->get_var("SELECT COUNT(id) FROM {$this->table} WHERE notified_at IS NULL");

        return [
            'total'   => $total,
            'pending' => $pending,
        ];
    }

    public function cleanup(): void
    {
        $threshold = gmdate('Y-m-d H:i:s', strtotime('-365 days'));
        $this->wpdb->query(
            $this->wpdb->prepare("DELETE FROM {$this->table} WHERE created_at < %s", $threshold)
        );
    }
}
