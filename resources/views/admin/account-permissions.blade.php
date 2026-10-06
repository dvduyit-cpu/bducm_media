<div class="full account-permissions"><h3>Quyền truy cập</h3>
@if($account?->isAdmin())<p class="form-note">Admin có toàn quyền quản lý và kiểm duyệt.</p>
@else
<input type="hidden" name="reviewer_control" value="1">
<label class="check-label reviewer-check"><input type="checkbox" name="reviewer_access" value="1" @checked($account?->reviewer_access ?? false)>Có quyền kiểm duyệt (toàn đơn vị)</label>
<p class="form-note">Admin tick để cấp quyền nhận, xem, duyệt, yêu cầu bổ sung, chọn đơn vị hỗ trợ và đóng sự kiện của toàn đơn vị. Vai trò của người dùng được giữ nguyên. Bỏ tick để thu hồi quyền kiểm duyệt.</p>
<p class="form-note">Quyền kiểm duyệt bao gồm Sự kiện phòng ban và Duyệt & điều phối. Các chức năng khác cần được cấp theo vai trò.</p>
<div class="permission-grid">@foreach($permissionOptions as $key=>$label)@if($key!=='approvals')<label class="check-label"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key,old('permissions',$account ? array_keys(array_filter($permissionOptions,fn($label,$key)=>$account->canAccess($key),ARRAY_FILTER_USE_BOTH)) : ['dashboard','requests','calendar'])))>{{ $label }}</label>@endif @endforeach</div>
<label class="check-label" data-global-scope><input type="checkbox" name="view_all_units" value="1" @checked(old('view_all_units',$account?->view_all_units ?? false))>Cho xem toàn bộ phòng ban (VP BGĐ/BGĐ không kiểm duyệt)</label>
<p class="form-note" data-reviewer-scope-note hidden>Chưa được cấp kiểm duyệt toàn đơn vị. Tài khoản chỉ xem dữ liệu trong phạm vi phòng ban được cấp.</p>
@endif</div>
