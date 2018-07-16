---
--- Created by xingzhengmao.
--- DateTime: 2018/1/4 下午6:13
---
local key = KEYS[1]
local opUid = ARGV[1]
local opAmount = ARGV[2]
local weekAmount = redis.call('zscore', key, opUid)
if weekAmount then
    weekAmount = weekAmount + opAmount
else
    weekAmount = opAmount
end
redis.call('zadd', key, weekAmount, opUid)
return true