local taskKey = KEYS[1]
local findKey = 'share_count'
local findKey2 = 'total_share_count'
local opType = tonumber(ARGV[1])
if opType == 1 then
    findKey = 'click_count'
    findKey2 = 'total_click_count'
end
local opCount = redis.call('hget', taskKey, findKey)
local totalOpCount = redis.call('hget', taskKey, findKey2)
local balance = 0
local opAmount = tonumber(ARGV[2])
local costBalance = 0
    balance = redis.call('hget', taskKey, 'balance') * 100
    opAmount = tonumber(ARGV[2])
    costBalance = opAmount
opCount = tonumber(opCount) + 1
totalOpCount = tonumber(totalOpCount) + 1
if (balance >= costBalance) then
    balance = tostring((balance - costBalance) * 0.01)
    balance = string.format("%.4f", balance)
    if (balance == 0) then
        redis.call('hmset', taskKey, 'status', 2, 'balance', balance, findKey, opCount, findKey2, totalOpCount)
    else
        redis.call('hmset', taskKey, 'balance', balance, findKey, opCount, findKey2, totalOpCount)
    end
    return {opCount, opAmount}
else
    return -100
end