--- Created by pangpang.
--- DateTime: 2017/12/20 上午11:04

local key = KEYS[1]
local data = cjson.decode(ARGV[1])
for i, v in ipairs(data) do
    redis.call('ZADD', key, v, i)
end
redis.call('EXPIRE', key, 86401)
return true

