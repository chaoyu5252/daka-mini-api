---
--- Created by pangpang.
--- DateTime:       2017/12/20 下午2:07
--- Description:    get grab redpack permissions
---

local key = KEYS[1]
local uid = ARGV[1]
local max_count = tonumber(ARGV[2])
local rs = redis.call('HGET', key, uid)
if rs then
    return -1
end
local grab_count = redis.call('HLEN', key)
if grab_count < max_count then
    if rs then
        return -1
    else
        redis.call('HSET', key, uid, 100)
        return max_count - (grab_count + 1)
    end
end
return -1

