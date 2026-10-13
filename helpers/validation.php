<?php
/**
 * Helper Validation: Memastikan kepatuhan aturan bisnis inventory
 */

function validateProduct(array $data, ?int $currentId = null, PDO $pdo): array {
    $errors = [];

    $name = trim($data['name'] ?? '');
    $sku = trim($data['sku'] ?? '');
    $category_id = $data['category_id'] ?? '';
    $price = $data['price'] ?? '';
    $stock = $data['stock'] ?? '';

    // Nama produk minimal 3 karakter
    if ($name === '') {
        $errors[] = 'Nama produk sepatu wajib diisi.';
    } elseif (mb_strlen($name) < 3) {
        $errors[] = 'Nama produk minimal 3 karakter.';
    }

    // SKU wajib dan unik
    if ($sku === '') {
        $errors[] = 'Kode SKU produk wajib diisi.';
    } else {
        $sql = "SELECT id FROM products WHERE LOWER(sku) = LOWER(:sku)";
        $params = ['sku' => $sku];
        if ($currentId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $currentId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'Kode SKU sudah terdaftar pada produk lain. SKU harus unik.';
        }
    }

    // Cek Unik Nama
    $sqlName = "SELECT id FROM products WHERE LOWER(name) = LOWER(:name)";
    $paramsName = ['name' => $name];
    if ($currentId !== null) {
        $sqlName .= " AND id != :id";
        $paramsName['id'] = $currentId;
    }
    $stmtName = $pdo->prepare($sqlName);
    $stmtName->execute($paramsName);
    if ($stmtName->fetch()) {
        $errors[] = 'Nama produk sepatu sudah terdaftar.';
    }

    // Kategori
    if (empty($category_id) || !filter_var($category_id, FILTER_VALIDATE_INT)) {
        $errors[] = 'Kategori produk wajib dipilih.';
    }

    // Harga tidak boleh negatif dan harus > 0
    if ($price === '' || !is_numeric($price)) {
        $errors[] = 'Harga harus berupa angka valid.';
    } elseif ((float)$price < 0) {
        $errors[] = 'Harga produk tidak boleh bernilai negatif.';
    }

    // Stok tidak boleh negatif
    if ($stock === '' || (!filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) && $stock !== '0')) {
        $errors[] = 'Stok produk tidak boleh bernilai negatif (minimal 0).';
    }

    return $errors;
}
