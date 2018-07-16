---
--- Created by xingzhengmao.
--- DateTime: 2017/12/20 下午5:06
---

local key = KEYS[1]
local opAmount = ARGV[1]
-- update Redpack data
local rpd = redis.call('HGETALL', key)
-- process hgetall
local rpd2 = {}
local k = ""
for idx, v in pairs(rpd) do
    if ((idx + 1) % 2 == 0) then
        k = v
    else
        rpd2[k] = v
    end
end
-- redpack balance
local balance = tonumber(rpd2['balance'])
balance = balance - opAmount
if balance < 0 then
    balance = 0
end
-- if balance equipment zero, redpack is over
local status = tonumber(rpd2['status'])
if balance == 0 then
    status = 1
end
-- update redpack data
redis.call('HMSET', key, 'balance', balance, 'status', status)
-- return data
return cjson.encode({['status'] = status, ['balance'] = balance})