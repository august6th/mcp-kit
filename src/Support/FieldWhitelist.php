<?php

namespace Erp\McpKit\Support;

/**
 * 出参白名单 + 脱敏。
 *
 * 默认拒绝：只有列在 allowed 里的字段才会出现在结果里；piiFields 内的字段值会被掩码。
 * allowed 支持嵌套：['order_code', 'products' => ['goods_sn', 'qty']]。
 * piiFields 同样支持嵌套：['email', 'recipient' => ['phone']]。
 */
class FieldWhitelist
{
    private $allowed;
    private $piiFields;

    public function __construct($allowed, $piiFields = [])
    {
        $this->allowed = is_array($allowed) ? $allowed : [];
        $this->piiFields = is_array($piiFields) ? $piiFields : [];
    }

    public function applyToList($rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->applyToRow($row);
        }
        return $out;
    }

    public function applyToRow($row)
    {
        $row = is_array($row) ? $row : (array)$row;
        return $this->filterAssoc($row, $this->allowed, $this->piiFields);
    }

    private function filterAssoc($row, $allowed, $piiFields)
    {
        $filtered = [];
        foreach ($allowed as $key => $spec) {
            if (is_int($key)) {
                $field = $spec;
                $childSpec = null;
            } else {
                $field = $key;
                $childSpec = is_array($spec) ? $spec : null;
            }
            if (! array_key_exists($field, $row)) {
                continue;
            }
            $value = $row[$field];
            if ($childSpec !== null) {
                $nestedPii = $this->nestedPii($piiFields, $field);
                $filtered[$field] = $this->filterNestedValue($value, $childSpec, $nestedPii);
                continue;
            }
            if ($this->isPiiField($piiFields, $field)) {
                $value = $this->mask($value);
            }
            $filtered[$field] = $value;
        }
        return $filtered;
    }

    private function filterNestedValue($value, $childSpec, $nestedPii)
    {
        if (! is_array($value)) {
            return $value;
        }
        if ($this->isList($value)) {
            $out = [];
            foreach ($value as $item) {
                $item = is_array($item) ? $item : (array)$item;
                $out[] = $this->filterAssoc($item, $childSpec, $nestedPii);
            }
            return $out;
        }
        return $this->filterAssoc($value, $childSpec, $nestedPii);
    }

    private function nestedPii($piiFields, $field)
    {
        if (isset($piiFields[$field]) && is_array($piiFields[$field])) {
            return $piiFields[$field];
        }
        return [];
    }

    private function isPiiField($piiFields, $field)
    {
        foreach ($piiFields as $key => $spec) {
            if (is_int($key) && $spec === $field) {
                return true;
            }
        }
        return false;
    }

    private function isList($value)
    {
        if ($value === []) {
            return true;
        }
        $i = 0;
        foreach ($value as $key => $unused) {
            if ($key !== $i) {
                return false;
            }
            $i++;
        }
        return true;
    }

    /**
     * 通用掩码：邮箱保留首字符 + 域名；其余字符串保留首尾字符，中间打星。
     * @param mixed $value
     * @return mixed
     */
    public function mask($value)
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }
        if (strpos($value, '@') !== false) {
            $parts = explode('@', $value, 2);
            $head = mb_substr($parts[0], 0, 1);
            return $head . '***@' . $parts[1];
        }
        $len = mb_strlen($value);
        if ($len <= 2) {
            return mb_substr($value, 0, 1) . '*';
        }
        return mb_substr($value, 0, 1) . str_repeat('*', $len - 2) . mb_substr($value, -1);
    }
}
