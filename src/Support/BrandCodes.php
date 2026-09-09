<?php

namespace Erp\McpKit\Support;

/**
 * 品牌级数据权限解析，语义与 AccountCodes::resolve 相同。
 */
class BrandCodes
{
    /**
     * @param array $userCodes
     * @param array|string $requested
     * @param bool $isSuperuser
     * @return array
     */
    public static function resolve($userCodes, $requested, $isSuperuser)
    {
        return AccountCodes::resolve($userCodes, $requested, $isSuperuser);
    }
}
