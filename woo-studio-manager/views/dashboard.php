<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wsm-wrap" dir="rtl">
    <h1 class="wp-heading-inline">مدیریت محصولات</h1>
    <hr class="wp-header-end">

    <!-- Top Dashboard Area -->
    <div class="wsm-dashboard-cards">
        <div class="wsm-card">
            <div class="wsm-card-icon dashicons dashicons-products"></div>
            <div class="wsm-card-info">
                <h4 class="wsm-card-title">کل محصولات</h4>
                <div class="wsm-card-value" id="stat-total">0</div>
            </div>
        </div>
        <div class="wsm-card wsm-card-danger">
            <div class="wsm-card-icon dashicons dashicons-warning"></div>
            <div class="wsm-card-info">
                <h4 class="wsm-card-title">ناموجود</h4>
                <div class="wsm-card-value" id="stat-outofstock">0</div>
            </div>
        </div>
        <div class="wsm-card wsm-card-safe">
            <div class="wsm-card-icon dashicons dashicons-edit"></div>
            <div class="wsm-card-info">
                <h4 class="wsm-card-title">ویرایش‌های در انتظار</h4>
                <div class="wsm-card-value" id="stat-pending">0</div>
            </div>
        </div>
    </div>

    <!-- Hidden Bulk Action Bar -->
    <div id="wsm-bulk-bar" class="wsm-bulk-bar" style="display: none;">
        <div class="wsm-bulk-info">
            <span id="wsm-bulk-count-text">0 محصول انتخاب شده</span>
        </div>
        <div class="wsm-bulk-actions">
            <label for="wsm-bulk-price-input">قیمت جدید گروهی:</label>
            <input type="number" id="wsm-bulk-price-input" min="0" step="1000" placeholder="مبلغ به تومان...">
            <button type="button" id="wsm-bulk-apply-btn" class="button button-primary">اعمال به انتخاب‌شده‌ها</button>
        </div>
    </div>

    <!-- DataTables Container -->
    <div class="wsm-table-container">
        <table id="wsm-products-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th class="dt-checkbox-header"><input type="checkbox" id="wsm-select-all"></th>
                    <th>شناسه</th>
                    <th>تصویر</th>
                    <th>عنوان محصول</th>
                    <th>شناسه محصول (SKU)</th>
                    <th>نوع</th>
                    <th>دسته‌بندی‌ها</th>
                    <th>موجودی</th>
                    <th>قیمت اصلی</th>
                    <th>قیمت فروش ویژه</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated via AJAX -->
            </tbody>
        </table>
    </div>

    <!-- Floating Action Button -->
    <div class="wsm-fab-container">
        <button type="button" id="wsm-fab-save" class="wsm-fab" style="display: none;">
            <span class="dashicons dashicons-saved"></span>
            <span id="wsm-fab-text">ذخیره تغییرات (0 محصول)</span>
        </button>
    </div>
</div>
