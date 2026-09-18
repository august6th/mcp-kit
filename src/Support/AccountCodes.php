<?php

namespace Erp\McpKit\Support;

/**
 * 账号级数据权限解析。
 *
 * 语义对齐 OMS App\Services\Common\Params::withPermissions：
 *  - 超管（isSuperuser=true）：不限制，返回 []（下游 whereIn 空数组=不加过滤=查全部）
 *  - 授权账号为空：不限制；无 requested 返回 []，有 requested 则按 requested 收窄
 *  - 有授权账号：
 *      · AI 未指定 requested：用用户授权全集
 *      · AI 指定 requested：授权全集 ∩ requested；交集为空 → ['unknown']（越权即全拒绝）
 *
 * 关键点：非空授权集时 AI 只能收窄不能放大；空授权集与超管一样表示全部店铺。
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
        $requested = self::normalize($requested);
        if (empty($userCodes)) {
            return $requested;
        }

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
