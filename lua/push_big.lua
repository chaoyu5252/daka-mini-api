---
--- Created by xingzhengmao.
--- DateTime: 2017/12/15 下午1:38
--- KEYS[1]: 操作的KEY
--- ARGV[1]: 操作的ID
--- ARGV[2]: 操作的值
---

local bigKey = KEYS[1]
local id = ARGV[1]
local pushValue = tonumber(ARGV[2])
redis.call('ZADD', bigKey, ARGV[2], id)
local rs = redis.call('ZREVRANGE', bigKey, 0, 0)
if rs then
    local maxId = rs[1]
    if maxId ~= nil then
        local maxValue = tonumber(redis.call('ZSCORE', bigKey, maxId))
        if maxValue ~= nil then
            if maxValue < pushValue then
                return 1
            else
                return 0
            end
        end
        return 1
    end
    return 1
else
    return 1
end
