<?php

return [
    'label' => 'Chỉ số điện nước',
    'plural_label' => 'Chỉ số điện nước',
    'previous_help' => 'Nhập chỉ số đầu khi ghi lần đầu hoặc chuyển từ thu theo người/cố định về đồng hồ. Không tính lại nước của các tháng đã thu theo cách khác. Các tháng liên tiếp dùng chỉ số cuối đã ghi.',
    'immutable_help' => 'Dùng Sửa chỉ số cho lần ghi mới nhất; bắt buộc lý do và lưu lịch sử. Nếu đã lập hóa đơn, cần hủy trước rồi lập bản thay thế. Không sửa chỉ số đã có kỳ sau.',
    'bulk' => [
        'title' => 'Ghi chỉ số theo nhà',
        'single' => 'Ghi từng phòng',
        'history' => 'Lịch sử chỉ số',
        'load' => 'Tải danh sách phòng',
        'save' => 'Lưu chỉ số đã nhập',
        'help' => 'Chọn nhà và tháng sử dụng điện nước. Chỉ nhập dịch vụ tính theo đồng hồ; để trống chỉ số cuối để ghi sau. Nếu có lỗi, toàn bộ lượt nhập sẽ chưa được lưu.',
        'opening_help' => 'Chỉ số đầu tự lấy từ lần ghi trước. Ô trống cần nhập chỉ số mở đầu đồng hồ.',
        'choose' => 'Chọn nhà và tháng, rồi tải danh sách phòng để bắt đầu.',
        'empty' => 'Nhà này chưa có phòng hoặc không có dịch vụ tính theo đồng hồ trong tháng đã chọn.',
        'not_metered' => 'Không tính theo đồng hồ',
        'recorded' => 'Đã ghi',
        'pending' => 'Chưa ghi',
        'saved' => 'Đã lưu :count chỉ số.',
        'enter_reading' => 'Nhập ít nhất một chỉ số cuối để lưu.',
        'reload' => 'Nhà hoặc tháng đã thay đổi. Tải lại danh sách phòng trước khi lưu.',
    ],
    'fields' => [
        'property' => 'Nhà', 'room' => 'Phòng', 'service' => 'Dịch vụ', 'billing_period' => 'Tháng tính phí',
        'previous_value' => 'Chỉ số đầu', 'current_value' => 'Chỉ số cuối', 'usage' => 'Tiêu thụ',
        'recorded_at' => 'Thời điểm ghi', 'recorded_by' => 'Người ghi', 'notes' => 'Ghi chú',
    ],
    'errors' => [
        'room_unavailable' => 'Chọn phòng thuộc nhà của bạn.',
        'service_unavailable' => 'Chọn dịch vụ đo chỉ số đang hoạt động và được bật cho nhà trong tháng tính phí.',
        'period_order' => 'Đã có chỉ số cho tháng này hoặc tháng sau. Cần nhập theo thứ tự tháng.',
        'initial_value_required' => 'Nhập chỉ số đầu cho lần ghi đầu tiên hoặc khi bắt đầu lại cách thu theo đồng hồ.',
        'previous_mismatch' => 'Chỉ số đầu phải bằng chỉ số cuối của lần ghi trước.',
        'decreasing_value' => 'Chỉ số cuối phải lớn hơn hoặc bằng chỉ số đầu.',
        'immutable' => 'Chỉ số đã ghi không được sửa hoặc xóa. Sai sót cần luồng điều chỉnh riêng.',
    ],
];
