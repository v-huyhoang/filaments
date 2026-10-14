<?php

return [
    'label' => 'Hóa đơn',
    'plural_label' => 'Hóa đơn',
    'rent' => 'Tiền thuê phòng',
    'occupant_help' => 'Số người ở thực tế, bắt buộc khi có phí theo người. Không được vượt sức chứa phòng.',
    'generation_help' => 'Tính đủ tiền thuê tháng; giá dịch vụ theo cấu hình cuối tháng. Dịch vụ thủ công không tự tính. Số tiền đã xuất không được sửa.',
    'fields' => [
        'number' => 'Số hóa đơn', 'contract' => 'Hợp đồng', 'billing_period' => 'Tháng tính phí',
        'occupant_count' => 'Số người ở thực tế', 'property' => 'Nhà', 'room' => 'Phòng', 'tenant' => 'Khách thuê',
        'status' => 'Trạng thái', 'issued_at' => 'Thời điểm xuất', 'due_date' => 'Hạn thanh toán', 'subtotal' => 'Tạm tính',
        'total' => 'Tổng tiền', 'notes' => 'Ghi chú', 'items' => 'Chi tiết hóa đơn', 'name' => 'Khoản phí',
        'quantity' => 'Số lượng', 'unit' => 'Đơn vị', 'unit_price' => 'Đơn giá', 'amount' => 'Thành tiền',
    ],
    'statuses' => ['draft' => 'Nháp', 'issued' => 'Đã xuất', 'partial' => 'Thanh toán một phần', 'paid' => 'Đã thanh toán', 'overdue' => 'Quá hạn', 'cancelled' => 'Đã hủy'],
    'actions' => ['cancel' => 'Hủy hóa đơn'],
    'errors' => [
        'contract_unavailable' => 'Chọn hợp đồng thuộc nhà và khách thuê của bạn.',
        'contract_not_billable' => 'Cần hợp đồng có thời gian thuê thực tế giao với kỳ thu. Hợp đồng đã trả phòng vẫn lập được hóa đơn các kỳ đã thuê.',
        'duplicate' => 'Đã có hóa đơn cho kỳ này. Muốn thay hóa đơn đã hủy, chọn Lập hóa đơn thay thế trên trang chi tiết hóa đơn đó.',
        'missing_reading' => 'Cần chỉ số :service trong tháng tính phí này.',
        'occupant_count_required' => 'Nhập số người ở thực tế để tính phí theo người.',
        'occupant_count_exceeds_capacity' => 'Số người ở vượt sức chứa phòng.',
        'immutable' => 'Dữ liệu chốt hóa đơn không được sửa hoặc xóa. Hãy hủy hóa đơn thay thế.',
        'amount_out_of_range' => 'Số tiền hóa đơn tính được vượt phạm vi hỗ trợ.',
        'invalid_status_transition' => 'Không hỗ trợ chuyển trạng thái hóa đơn này.',
    ],
];
