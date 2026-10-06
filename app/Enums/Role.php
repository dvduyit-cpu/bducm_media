<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Director = 'director';
    case Office = 'office';
    case Head = 'head';
    case Staff = 'staff';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin', self::Director => 'Ban Giám đốc', self::Office => 'Văn phòng BGĐ',
            self::Head => 'Trưởng đơn vị', self::Staff => 'Nhân viên đơn vị', self::Media => 'Nhân sự truyền thông',
        };
    }
}
