@extends('layouts.app')
@section('title',$item->exists ? 'Sửa sự kiện' : 'Gửi sự kiện mới')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ĐĂNG KÝ SỰ KIỆN</div><h1>{{ $item->exists ? 'Sửa nội dung sự kiện' : 'Gửi sự kiện mới' }}</h1><p>Lưu nháp, bổ sung tài liệu rồi gửi Văn phòng BGĐ kiểm tra và duyệt.</p></div><a class="button secondary" href="{{ $item->exists ? route('requests.show',$item) : route('requests.index') }}">Quay lại</a></div>
<form class="panel form-panel" method="post" action="{{ $item->exists ? route('requests.update',$item) : route('requests.store') }}">@csrf @if($item->exists)@method('PUT')@endif
<div class="form-grid">
<label class="full">Tên sự kiện <b>*</b><input name="title" required maxlength="255" value="{{ old('title',$item->title) }}"></label>
<label>Phòng ban chủ trì <b>*</b><select id="department-select" name="department_id" required @disabled($item->exists)>@foreach($departments as $unit)<option value="{{ $unit->id }}" @selected(old('department_id',$item->department_id ?? auth()->user()->department_id)==$unit->id)>{{ $unit->name }}</option>@endforeach</select>@if($item->exists)<input type="hidden" name="department_id" value="{{ $item->department_id }}">@endif</label>
<label>Người đầu mối <b>*</b><select name="contact_id" id="contact-select" required>@foreach($contacts as $person)<option value="{{ $person->id }}" data-department="{{ $person->department_id }}" @selected(old('contact_id',$item->contact_id ?? auth()->id())==$person->id)>{{ $person->name }} · {{ $person->department?->code }}</option>@endforeach</select></label>
<label>Thời gian bắt đầu <b>*</b><input type="datetime-local" name="event_at" required value="{{ old('event_at',$item->event_at?->format('Y-m-d\TH:i')) }}"></label>
<label>Thời gian kết thúc<input type="datetime-local" name="event_ends_at" value="{{ old('event_ends_at',$item->event_ends_at?->format('Y-m-d\TH:i')) }}"></label>
<label class="full">Địa điểm<input name="location" maxlength="255" value="{{ old('location',$item->location) }}"></label>
<label class="full">Nội dung sự kiện & nhu cầu phối hợp <b>*</b><textarea name="description" rows="8" required maxlength="30000" placeholder="Nội dung, đối tượng tham gia và công việc cần chuẩn bị; ghi rõ nhu cầu nếu cần phòng ban khác hỗ trợ.">{{ old('description',$item->description) }}</textarea></label>
<label>Mức độ ưu tiên<select name="priority">@foreach(['low'=>'Thấp','normal'=>'Bình thường','high'=>'Cao','urgent'=>'Khẩn cấp'] as $key=>$label)<option value="{{ $key }}" @selected(old('priority',$item->priority ?? 'normal')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Màu thẻ sự kiện<input name="task_color" type="color" value="{{ old('task_color',$item->task_color ?? auth()->user()->theme_color ?? '#2563eb') }}"></label>
</div><div class="form-note">VP BGĐ sẽ quyết định tự chủ hoặc chọn phòng ban hỗ trợ khi duyệt. Bạn có thể thêm tệp và liên kết sau khi lưu nháp.</div><div class="form-actions"><button class="button primary" type="submit">Lưu {{ $item->exists ? 'thay đổi' : 'bản nháp' }}</button></div></form>
@endsection