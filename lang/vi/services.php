<?php

return [
    'label' => 'dịch vụ',
    'plural_label' => 'dịch vụ',
    'property_services' => 'Dịch vụ của bất động sản',
    'configuration' => 'cấu hình dịch vụ',
    'price_history_help' => 'Thay đổi giá tạo phiên bản mới với ngày hiệu lực muộn hơn; phiên bản cũ được giữ nguyên.',
    'fields' => [
        'name' => 'Tên dịch vụ',
        'code' => 'Mã dịch vụ',
        'billing_method' => 'Cách tính phí',
        'unit' => 'Đơn vị',
        'is_active' => 'Đang hoạt động',
        'unit_price' => 'Đơn giá',
        'fixed_price' => 'Phí cố định',
        'is_enabled' => 'Bật dịch vụ',
        'effective_from' => 'Có hiệu lực từ',
    ],
    'billing_methods' => [
        'metered' => 'Theo chỉ số',
        'fixed' => 'Cố định',
        'per_person' => 'Theo người',
        'manual' => 'Nhập thủ công',
    ],
    'actions' => [
        'configure' => 'Thêm dịch vụ',
        'revise' => 'Đổi giá / bật tắt',
    ],
    'errors' => [
        'definition_in_use' => 'Dịch vụ đã được cấu hình; không thể đổi cách tính phí hoặc đơn vị để bảo toàn lịch sử.',
        'service_unavailable' => 'Dịch vụ đã chọn không khả dụng.',
        'already_configured' => 'Dịch vụ này đã được cấu hình cho bất động sản; hãy tạo phiên bản giá mới.',
        'stale_version' => 'Cấu hình đã có phiên bản mới hơn. Vui lòng tải lại trước khi cập nhật.',
        'later_effective_date' => 'Ngày hiệu lực phải sau phiên bản gần nhất.',
    ],
];
