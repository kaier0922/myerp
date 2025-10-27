<?php
session_start();
require_once 'config.php';

$_SESSION['user_id'] = 1;

$_POST = [
    'customer_id' => 1,
    'quote_date' => date('Y-m-d'),
    'valid_days' => 15,
    'template_type' => 'assembled_pc',
    'status' => '草稿',
    'discount' => 0,
    'final_amount' => 1000,
    'items' => [
        [
            'category_id' => 15,
            'category' => '显卡',
            'product_id' => 'custom',
            'custom_name' => '测试显卡RTX5090',
            'custom_supplier' => '七彩虹',
            'spec' => '24GB GDDR7',
            'unit' => '个',
            'quantity' => 1,
            'price' => 9999
        ]
    ]
];

include 'quote_save_v2.php';
?>