<?php

namespace Zca\Models;

/**
 * Port of enum `BinBankCard` (src/models/Bank.ts)
 */
final class BinBankCard
{
    /**
     * NH TMCP An Bình
     */
    public const ABBank = 970425;
    /**
     * NH TMCP Á Châu
     */
    public const ACB = 970416;
    /**
     * NH Nông nghiệp và Phát triển Nông thôn Việt Nam
     */
    public const Agribank = 970405;
    /**
     * NH TMCP Đầu tư và Phát triển Việt Nam
     */
    public const BIDV = 970418;
    /**
     * Ngân hàng BNP Paribas - CN TP. Hồ Chí Minh
     */
    public const BNP_Paribas_HCM = 963666;
    /**
     * Ngân hàng BNP Paribas - CN Hà Nội
     */
    public const BNP_Paribas_HN = 963668;
    /**
     * NH TMCP Bản Việt
     */
    public const BVBank = 970454;
    /**
     * NH TMCP Bắc Á
     */
    public const BacA_Bank = 970409;
    /**
     * NH TMCP Bảo Việt
     */
    public const BaoViet_Bank = 970438;
    /**
     * NH số CAKE by VPBank - TMCP Việt Nam Thịnh Vượng
     */
    public const CAKE = 546034;
    /**
     * Ngân hàng Cathay United - CN TP. Hồ Chí Minh
     */
    public const Cathay_United_HCM = 168999;
    /**
     * NH Thương mại TNHH MTV Xây dựng Việt Nam
     *
     * @note Also observed as "VCBNeo" in supported banks list (same bin) - Old is bank: CB_Bank
     */
    public const VCBNeo = 970444;
    /**
     * NH TNHH MTV CIMB Việt Nam
     */
    public const CIMB_Bank = 422589;
    /**
     * NH Hợp tác xã Việt Nam
     */
    public const Coop_Bank = 970446;
    /**
     * NH TNHH MTV Phát triển Singapore - CN TP. Hồ Chí Minh
     */
    public const DBS_Bank = 796500;
    /**
     * NH TMCP Đông Á
     */
    public const DongA_Bank = 970406;
    /**
     * NH TMCP Xuất Nhập khẩu Việt Nam
     */
    public const Eximbank = 970431;
    /**
     * Ngân hàng Citibank Việt Nam
     */
    public const Citibank = 533948;
    /**
     * NH TMCP Dầu khí Toàn cầu
     */
    public const GPBank = 970408;
    /**
     * NH TMCP Phát triển TP. Hồ Chí Minh
     */
    public const HDBank = 970437;
    /**
     * NH TNHH MTV HSBC (Việt Nam)
     */
    public const HSBC = 458761;
    /**
     * NH TNHH MTV Hong Leong Việt Nam
     */
    public const HongLeong_Bank = 970442;
    /**
     * NH Công nghiệp Hàn Quốc - CN TP. Hồ Chí Minh
     */
    public const IBK_HCM = 970456;
    /**
     * NH Công nghiệp Hàn Quốc - CN Hà Nội
     */
    public const IBK_HN = 970455;
    /**
     * NH TNHH Indovina
     */
    public const Indovina_Bank = 970434;
    /**
     * NH Đại chúng TNHH Kasikornbank - CN TP. Hồ Chí Minh
     */
    public const KBank = 668888;
    /**
     * NH TMCP Kiên Long
     */
    public const KienlongBank = 970452;
    /**
     * NH Kookmin - CN TP. Hồ Chí Minh
     */
    public const Kookmin_Bank_HCM = 970463;
    /**
     * NH Kookmin - CN Hà Nội
     */
    public const Kookmin_Bank_HN = 970462;
    /**
     * Liobank by OCB
     */
    public const Liobank = 963369;
    /**
     * NH TMCP Lộc Phát Việt Nam
     */
    public const LPBank = 970449;
    /**
     * NH TMCP Quân đội
     */
    public const MB_Bank = 970422;
    /**
     * NH TMCP Hàng Hải
     */
    public const MSB = 970426;
    /**
     * MoMo
     */
    public const MoMo = 971025;
    /**
     * NH TMCP Quốc Dân
     */
    public const NCB = 970419;
    /**
     * NH TMCP Nam Á
     */
    public const Nam_A_Bank = 970428;
    /**
     * NH Nonghyup - CN Hà Nội
     */
    public const NongHyup_Bank = 801011;
    /**
     * NH TMCP Phương Đông
     */
    public const OCB = 970448;
    /**
     * NH Thương mại TNHH MTV Đại Dương
     */
    public const Ocean_Bank = 970414;
    /**
     * NH TMCP Thịnh vượng và Phát triển
     */
    public const PGBank = 970430;
    /**
     * NH TMCP Đại Chúng Việt Nam
     */
    public const PVcomBank = 970412;
    /**
     * NH TNHH MTV Public Việt Nam
     */
    public const Public_Bank_Vietnam = 970439;
    /**
     * NH TMCP Sài Gòn
     */
    public const SCB = 970429;
    /**
     * NH TMCP Sài Gòn - Hà Nội
     */
    public const SHB = 970443;
    /**
     * NH TMCP Sài Gòn Thương Tín
     */
    public const Sacombank = 970403;
    /**
     * NH TMCP Sài Gòn Công Thương
     */
    public const Saigon_Bank = 970400;
    /**
     * NH TMCP Đông Nam Á
     */
    public const SeABank = 970440;
    /**
     * NH TNHH MTV Shinhan Việt Nam
     */
    public const Shinhan_Bank = 970424;
    /**
     * NH TNHH MTV Standard Chartered Bank Việt Nam
     */
    public const Standard_Chartered_Vietnam = 970410;
    /**
     * NH số TNEX
     */
    public const TNEX = 963326;
    /**
     * NH TMCP Tiên Phong
     */
    public const TPBank = 970423;
    /**
     * NH TMCP Kỹ thương Việt Nam
     */
    public const Techcombank = 970407;
    /**
     * NH số Timo by Bản Việt Bank
     */
    public const Timo = 963388;
    /**
     * NH số UBank by VPBank
     */
    public const UBank = 546035;
    /**
     * NH United Overseas Bank Việt Nam
     */
    public const United_Overseas_Bank_Vietnam = 970458;
    /**
     * NH TMCP Quốc tế Việt Nam
     */
    public const VIB = 970441;
    /**
     * NH TMCP Việt Nam Thịnh Vượng
     */
    public const VPBank = 970432;
    /**
     * NH Liên doanh Việt - Nga
     */
    public const VRB = 970421;
    /**
     * NH TMCP Việt Á
     */
    public const VietABank = 970427;
    /**
     * NH TMCP Việt Nam Thương Tín
     */
    public const VietBank = 970433;
    /**
     * NH TMCP Ngoại Thương Việt Nam
     */
    public const Vietcombank = 970436;
    /**
     * NH TMCP Công thương Việt Nam
     */
    public const VietinBank = 970415;
    /**
     * NH TNHH MTV Woori Việt Nam
     */
    public const Woori_Bank = 970457;
}
