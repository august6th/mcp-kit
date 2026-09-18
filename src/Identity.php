<?php

namespace Erp\McpKit;

/**
 * 调用人身份值对象。
 *
 * 收敛"从 login_user 结构解析身份"这一步，将来切 OIDC 时只需改 fromLoginUser 的映射。
 * account_codes/brand_codes 是调用人被授权的数据范围；空数组=不限制。is_superuser 供工具级授权旁路。
 */
class Identity
{
    public $tenantId;
    public $userId;
    public $name;
    public $userName;
    public $accountCodes;
    public $brandCodes;
    public $isSuperuser;
    public $mcpClientId;

    public function __construct($attributes = [])
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $this->tenantId = isset($attributes['tenant_id']) ? (int)$attributes['tenant_id'] : 0;
        $this->userId = isset($attributes['user_id']) ? (int)$attributes['user_id'] : 0;
        $this->name = isset($attributes['name']) ? (string)$attributes['name'] : '';
        $this->userName = isset($attributes['user_name']) ? (string)$attributes['user_name'] : '';
        $this->accountCodes = isset($attributes['user_account_codes']) && is_array($attributes['user_account_codes'])
            ? array_values($attributes['user_account_codes']) : [];
        $this->brandCodes = isset($attributes['user_brand_codes']) && is_array($attributes['user_brand_codes'])
            ? array_values($attributes['user_brand_codes']) : [];
        $this->isSuperuser = ! empty($attributes['is_superuser']);
        $this->mcpClientId = isset($attributes['mcp_client_id']) ? (string)$attributes['mcp_client_id'] : '';
    }

    /**
     * 从中间件注入的 login_user 结构构造身份。
     * @param array $loginUser
     * @return Identity
     */
    public static function fromLoginUser($loginUser)
    {
        return new self($loginUser);
    }

    public function toArray()
    {
        return [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'name' => $this->name,
            'user_name' => $this->userName,
            'account_codes' => $this->accountCodes,
            'brand_codes' => $this->brandCodes,
            'is_superuser' => $this->isSuperuser,
            'mcp_client_id' => $this->mcpClientId,
        ];
    }
}
