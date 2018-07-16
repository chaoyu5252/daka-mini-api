local findKey = 'total_share_count'
local opType = tonumber(ARGV[1])
if opType == 1 then
    findKey = 'total_click_count'
end
local taskKey = KEYS[1]
local opCount = redis.call('hget', taskKey, findKey)
opCount = tonumber(opCount) + 1
redis.call('hset', taskKey, findKey, opCount)
return {opCount, 0}