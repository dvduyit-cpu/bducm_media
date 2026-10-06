<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\MediaRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Dữ liệu demo chỉ dùng trong local/testing.');
        }
        $units = collect([['VPBGD', 'Văn phòng Ban Giám đốc'], ['DT', 'Phòng Đào tạo'], ['CTSV', 'Phòng Công tác sinh viên'], ['TS', 'Trung tâm Tuyển sinh'], ['HTQT', 'Phòng Hợp tác quốc tế']])->map(fn ($row) => Department::firstOrCreate(['code' => $row[0]], ['name' => $row[1]]));
        $password = 'BduDemo@2026';
        $office = User::firstOrCreate(['email' => 'vanphong@bdu.local'], ['name' => 'Nguyễn Minh Anh', 'role' => 'office', 'department_id' => $units[0]->id, 'password' => $password]);
        User::firstOrCreate(['email' => 'giamdoc@bdu.local'], ['name' => 'Trần Quốc Minh', 'role' => 'director', 'password' => $password]);
        $media = User::firstOrCreate(['email' => 'truyenthong@bdu.local'], ['name' => 'Lê Hoàng Nam', 'role' => 'media', 'department_id' => $units[0]->id, 'password' => $password]);
        User::firstOrCreate(['email' => 'truongphong@bdu.local'], ['name' => 'Phạm Thu Hà', 'role' => 'head', 'department_id' => $units[1]->id, 'password' => $password]);
        User::firstOrCreate(['email' => 'nhanvien@bdu.local'], ['name' => 'Võ Thanh Thảo', 'role' => 'staff', 'department_id' => $units[1]->id, 'password' => $password]);
        foreach ($units->skip(2) as $unit) {
            User::firstOrCreate(['email' => strtolower($unit->code).'@bdu.local'], ['name' => 'Đầu mối '.$unit->name, 'role' => 'head', 'department_id' => $unit->id, 'password' => $password]);
        }
        foreach (['organization_name' => 'Phân hiệu Trường Đại học Bình Dương', 'default_color' => '#2563eb', 'deadline_day' => '5', 'deadline_time' => '16:00'] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        $titles = ['Ngày hội tư vấn tuyển sinh 2026', 'Thông báo lịch thi học kỳ', 'Chương trình chào đón tân sinh viên', 'Hội thảo hợp tác doanh nghiệp', 'Gương sinh viên tiêu biểu tháng 10', 'Tập huấn kỹ năng nghiên cứu khoa học', 'Lễ ký kết hợp tác quốc tế', 'Bản tin hoạt động tuần', 'Cuộc thi ý tưởng khởi nghiệp', 'Thông báo học bổng khuyến học', 'Workshop thiết kế sáng tạo', 'Tổng kết chiến dịch tình nguyện', 'Kế hoạch truyền thông tháng 11', 'Giới thiệu câu lạc bộ sinh viên', 'Lịch sinh hoạt đầu khóa'];
        $states = ['in_progress', 'submitted', 'approved', 'submitted', 'needs_info', 'approved', 'in_progress', 'completed', 'submitted', 'in_progress', 'in_progress', 'completed', 'submitted', 'submitted', 'draft'];
        foreach ($titles as $i => $title) {
            $unit = $units[1 + ($i % 4)];
            $contact = User::where('department_id', $unit->id)->where('role', 'head')->first();
            $status = $states[$i];
            $publish = now()->startOfWeek()->addDays($i % 7)->setTime(9 + ($i % 5), 0);
            $item = MediaRequest::firstOrCreate(['title' => $title], ['description' => 'Đề nghị phối hợp xây dựng nội dung truyền thông cho '.$title.'. Chuẩn bị bài viết, hình ảnh và thông tin chi tiết; phòng chủ trì cập nhật tiến độ và VP BGĐ kiểm tra, đóng sự kiện khi hoàn tất.',
                'department_id' => $unit->id, 'creator_id' => $contact->id, 'contact_id' => $contact->id,
                'assignee_id' => in_array($status, ['in_progress', 'assigned', 'pending_approval', 'completed', 'published', 'revision']) ? $media->id : null,
                'status' => $status, 'priority' => ['high', 'normal', 'normal', 'urgent', 'low'][$i % 5], 'channel' => ['Facebook', 'Website', 'Đa kênh'][$i % 3],
                'event_at' => $publish, 'event_ends_at' => $publish->copy()->addHours(4), 'due_at' => $publish->copy()->addHours(4),
                'coordination_mode' => in_array($status, ['approved', 'in_progress', 'completed']) ? 'autonomous' : 'pending', 'task_color' => ['#2563eb', '#7c3aed', '#15803d'][$i % 3], 'approved_by' => in_array($status, ['approved', 'in_progress', 'completed']) ? $office->id : null, 'approved_at' => in_array($status, ['approved', 'in_progress', 'completed']) ? now()->subDays(2) : null,
                'important' => $i === 6, 'progress' => in_array($status, ['completed', 'published']) ? 100 : ($status === 'in_progress' ? 65 : 0),
                'professional_checked_by' => $status === 'pending_approval' ? $contact->id : null,
                'product_notes' => in_array($status, ['pending_approval', 'published', 'completed']) ? 'Nội dung demo: '.$title.'. Đơn vị đã rà soát thông tin và sẵn sàng trình duyệt.' : null,
                'published_url' => in_array($status, ['published', 'completed']) ? 'https://example.com/bdu-demo/'.$i : null,
                'submitted_at' => $status === 'draft' ? null : now()->subDays(3), 'completed_at' => $status === 'completed' ? $publish->copy()->subHours(5) : null]);
            if ($item->wasRecentlyCreated) {
                $item->histories()->create(['user_id' => $office->id, 'action' => 'Khởi tạo dữ liệu minh họa', 'to_status' => $status, 'note' => 'Yêu cầu mẫu để trải nghiệm hệ thống.']);
            }
        }
    }
}
