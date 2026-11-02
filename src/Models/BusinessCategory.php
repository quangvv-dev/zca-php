<?php

namespace Zca\Models;

/**
 * Port of enum `BusinessCategory` (src/models/ZBusiness.ts)
 */
final class BusinessCategory
{
    public const Other = 0;
    public const RealEstate = 1;
    public const TechnologyAndDevices = 2;
    public const TravelAndHospitality = 3;
    public const EducationAndTraining = 4;
    public const ShoppingAndRetail = 5;
    public const CosmeticsAndBeauty = 6;
    public const RestaurantAndCafe = 7;
    public const AutoAndMotorbike = 8;
    public const FashionAndApparel = 9;
    public const FoodAndBeverage = 10;
    public const MediaAndEntertainment = 11;
    public const InternalCommunications = 12;
    public const Transportation = 13;
    public const Telecommunications = 14;

    public const NAMES = [
        self::Other => "Dịch vụ khác (Không hiển thị)",
        self::RealEstate => "Bất động sản",
        self::TechnologyAndDevices => "Công nghệ & Thiết bị",
        self::TravelAndHospitality => "Du lịch & Lưu trú",
        self::EducationAndTraining => "Giáo dục & Đào tạo",
        self::ShoppingAndRetail => "Mua sắm & Bán lẻ",
        self::CosmeticsAndBeauty => "Mỹ phẩm & Làm đẹp",
        self::RestaurantAndCafe => "Nhà hàng & Quán",
        self::AutoAndMotorbike => "Ô tô & Xe máy",
        self::FashionAndApparel => "Thời trang & May mặc",
        self::FoodAndBeverage => "Thực phẩm & Đồ uống",
        self::MediaAndEntertainment => "Truyền thông & Giải trí",
        self::InternalCommunications => "Truyền thông nội bộ",
        self::Transportation => "Vận tải",
        self::Telecommunications => "Viễn thông",
    ];
}
