local findKey = 'total_share_count'
local opType = tonumber(ARGV[1])
if opType == 1 then
    findKey = 'total_click_count'
end
local taskKey = KEYS[1]
local parentTaskKey = KEYS[2]
local opCount = redis.call('hget', taskKey, findKey)
local pOpCount = 0
opCount = tonumber(opCount) + 1
local balance = redis.call('hget', taskKey, 'balance')
if (parentTaskKey ~= "") then
    balance = redis.call('hget', parentTaskKey, 'balance')
    pOpCount = tonumber(redis.call('hget', parentTaskKey, findKey)) + 1
    redis.call('hset', parentTaskKey, findKey, pOpCount)
end
redis.call('hset', taskKey, findKey, opCount)
return {opCount, 0, 0}