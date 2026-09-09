<?php

namespace Erp\McpKit\Http;

use Closure;

/**
 * MCP 认证中间件。
 *
 * 以 OMS App\Http\Middleware\InnerSignatureCheck 为蓝本：同款 INNER_INTERFACE_KEY +
 * ksort/http_build_query 的 HMAC-SHA256 校验，local 环境跳过校验（沿用既有约定）。
 *
 * 与 InnerSignatureCheck 的差异：额外从签名后的 data 里取
 *   account_codes / brand_codes / is_superuser / mcp_client_id，
 * 注入 login_user（对齐 SignatureCheck 的 user_account_codes/user_brand_codes 结构），
 * 供下游工具做账号级数据权限与审计。
 *
 * 说明：第 1 步尚无网关，直接用 INNER_INTERFACE_KEY 模拟"将来网关代签"的请求；
 * 待第 2 步网关落地后，由网关持密钥代签，本中间件契约不变。
 */
class McpSignatureCheck
{
    public function handle($request, Closure $next)
    {
        try {
            $params = $this->getJsonParams($request);

            if ($this->isLocal()) {
                $timestamp = time();
            } else {
                if (empty($request->input('signature')) || empty($request->input('timestamp'))) {
                    return $this->fail(40003, 'signature AND timestamp required');
                }
                $timestamp = $request->input('timestamp');
                if (abs(time() - $timestamp) > 600) {
                    return $this->fail(40004, '请求已过期');
                }

                $signParams = $params;
                $signParams['timestamp'] = $timestamp;
                unset($signParams['signature']);
                ksort($signParams);
                $queryStr = http_build_query($signParams);
                $serverSignature = hash_hmac('sha256', $queryStr, env('INNER_INTERFACE_KEY', ''));
                $clientSignature = $request->input('signature');
                if (strtoupper($clientSignature) != strtoupper($serverSignature)) {
                    return $this->fail(40002, '签名错误');
                }
            }

            $tenantId = isset($params['tenant_id']) ? (int)$params['tenant_id'] : 0;
            if (! $tenantId) {
                return $this->fail(40001, '用户信息异常');
            }

            $loginUser = [
                'tenant_id' => $tenantId,
                'user_id' => isset($params['user_id']) ? (int)$params['user_id'] : 0,
                'name' => isset($params['name']) ? (string)$params['name'] : '',
                'user_name' => isset($params['user_name']) ? (string)$params['user_name'] : '',
                'secret' => '',
                'user_account_codes' => isset($params['account_codes']) && is_array($params['account_codes'])
                    ? array_values($params['account_codes']) : [],
                'user_brand_codes' => isset($params['brand_codes']) && is_array($params['brand_codes'])
                    ? array_values($params['brand_codes']) : [],
                'is_superuser' => ! empty($params['is_superuser']),
                'mcp_client_id' => isset($params['mcp_client_id']) ? (string)$params['mcp_client_id'] : '',
            ];

            $request->attributes->add(['login_user' => $loginUser]);
            config(['repository.pagination.limit' => isset($params['page_size']) ? $params['page_size'] : 20]);
        } catch (\Exception $ex) {
            $code = $ex->getCode() == 0 ? 50000 : $ex->getCode();
            return $this->fail($code, $ex->getMessage());
        }

        return $next($request);
    }

    private function isLocal()
    {
        return app()->environment() == 'local';
    }

    /**
     * 读取签名载荷（data 字段，JSON）。与 InnerSignatureCheck::getJsonParams 一致。
     * @param \Illuminate\Http\Request $request
     * @param string $key
     * @return array
     */
    private function getJsonParams($request, $key = 'data')
    {
        if ($request->getMethod() == 'GET') {
            $data = $request->get($key);
        } else {
            $data = $request->post($key);
        }
        empty($data) && $data = $request->query($key);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return is_array($data) ? $data : [];
    }

    private function fail($code, $message = '')
    {
        if ($message === '' && function_exists('getCodeDefinition')) {
            $message = getCodeDefinition($code);
        }
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => [],
        ]);
    }
}
