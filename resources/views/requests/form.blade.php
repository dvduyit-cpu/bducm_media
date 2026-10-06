@extends('layouts.app')
@section('title', $item->exists ? 'Chỉnh sửa yêu cầu' : 'Tạo yêu cầu')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ĐĂNG KÝ NHU CẦU</div><h1>{{ $item->exists ? 'Chỉnh sửa yêu cầu' : 'Tạo yêu cầu truyền thông' }}</h1><p>Cung cấp thông tin đầy đủ để Văn phòng BGĐ sắp xếp kế hoạch và nhân sự.</p></div><a class="button secondary" href="{{ $item->exists ? route('requests.show',$item) : route('requests.index') }}">Quay lại</a></div>
<form method="post" action="{{ $item->exists ? route('requests.update',$item) : route('requests.store') }}" class="panel form-panel">@csrf @if($item->exists)@method('PUT')@endif
<div class="form-section-title"><span>01</span><div><h2>Thông tin nội dung</h2><p>Tên hoạt động, đơn vị đề xuất và người đầu mối phối hợp</p></div></div>
<div class="form-grid">
<label class="full">Tên nội dung truyền thông <b>*</b><input name="title" value="{{ old('title',$item->title) }}" required maxlength="255" placeholder="Ví dụ: Ngày hội tư vấn tuyển sinh 2026"></label>
<label>Đơn vị đề xuất <b>*</b><select name="department_id" required id="department-select" @if($item->exists) disabled @endif>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id',$item->department_id ?? auth()->user()->department_id)==$department->id)>{{ $department->name }}</option>@endforeach</select>@if($item->exists)<input type="hidden" name="department_id" value="{{ $item->department_id }}">@endif</label>
<label>Người đầu mối phối hợp <b>*</b><select name="contact_id" required id="contact-select">@foreach($contacts as $contact)<option value="{{ $contact->id }}" data-department="{{ $contact->department_id }}" @selected(old('contact_id',$item->contact_id)==$contact->id)>{{ $contact->name }} · {{ $contact->department->code }}</option>@endforeach</select></label>
<label class="full">Mô tả & yêu cầu thực hiện <b>*</b><textarea rows="7" name="description" required maxlength="30000" placeholder="Mục tiêu, đối tượng, thông tin hoạt động, nội dung cần truyền thông và yêu cầu phối hợp…">{{ old('description',$item->description) }}</textarea></label>
</div>
<div class="form-section-title"><span>02</span><div><h2>Thời gian & kế hoạch</h2><p>Hạn hoàn thành sản phẩm cần trước hoặc bằng thời điểm đăng tải</p></div></div>
<div class="form-grid three">
<label>Thời gian diễn ra hoạt động <b>*</b><input type="datetime-local" name="event_at" value="{{ old('event_at',$item->event_at?->format('Y-m-d\\TH:i')) }}" required></label>
<label>Thời gian đề xuất đăng <b>*</b><input type="datetime-local" name="publish_at" value="{{ old('publish_at',$item->publish_at?->format('Y-m-d\\TH:i')) }}" required></label>
<label>Hạn hoàn thành sản phẩm <b>*</b><input type="datetime-local" name="due_at" value="{{ old('due_at',$item->due_at?->format('Y-m-d\\TH:i')) }}" required></label>
<label>Kênh truyền thông <b>*</b><select name="channel">@foreach(['Website','Facebook','Zalo','Email','Đa kênh'] as $channel)<option @selected(old('channel',$item->channel)===$channel)>{{ $channel }}</option>@endforeach</select></label>
<label>Mức độ ưu tiên đề xuất <b>*</b><select name="priority">@foreach(['low'=>'Thấp','normal'=>'Bình thường','high'=>'Cao','urgent'=>'Khẩn cấp'] as $value=>$label)<option value="{{ $value }}" @selected(old('priority',$item->priority ?? 'normal')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="check-label"><input type="checkbox" name="important" value="1" @checked(old('important',$item->important))>Nội dung quan trọng, cần BGĐ duyệt</label>
</div><div class="form-note"><x-icon name="folder"/><span>Sau khi lưu, bạn có thể đính kèm tài liệu, hình ảnh và trình duyệt yêu cầu.</span></div>
<div class="form-actions"><a class="button secondary" href="{{ route('requests.index') }}">Hủy thao tác</a><button class="button primary" type="submit">{{ $item->exists ? 'Lưu thay đổi' : 'Lưu bản nháp' }}</button></div>
</form>
@endsection