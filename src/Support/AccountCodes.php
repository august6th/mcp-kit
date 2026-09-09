<?php

namespace Erp\McpKit\Support;

/**
 * 账号级数据权限解析。
 *
 * 语义对齐 OMS App\Services\Common\Params::withPermissions：
 *  - 超管（isSuperuser=true）：不限制，返回 []（下游 whereIn 空数组=不加过滤=查全部）
 *  - 非超管、无授权账号：返回 ['unknown'] 哨兵（查不到任何数据）
 *  - 非超管、有授权账号：
 *      · AI 未指定 requested：用用户授权全集
 *      · AI 指定 requested：授权全集 ∩ requested；交集为空 → ['unknown']（越权即全拒绝）
 *
 * 关键点：权限来自签名注入的授权集，AI 只能在授权集内收窄，永远无法越权放大。
 */
class AccountCodes
{
    /**
     * @param array $userCodes 调用人被授权的账号编码
     * @param array|string $requested AI 请求收窄到的账号编码
     * @param bool $isSuperuser
     * @return array
     */
    public static function resolve($userCodes, $requested, $isSuperuser)
    {
        if ($isSuperuser) {
            return [];
        }

        $userCodes = self::normalize($userCodes);
        if (empty($userCodes)) {
            return ['unknown'];
        }

        $requested = self::normalize($requested);
        if (empty($requested)) {
            return array_values($userCodes);
        }

        $intersect = array_values(array_intersect($userCodes, $requested));
        if (empty($intersect)) {
            return ['unknown'];
        }
        return $intersect;
    }

    private static function normalize($codes)
    {
        if (is_string($codes)) {
            $codes = $codes === '' ? [] : [$codes];
        }
        if (! is_array($codes)) {
            return [];
        }
        $codes = array_filter($codes, function ($c) {
            return $c !== '' && $c !== null;
        });
        return array_values(array_unique($codes));
    }
}
