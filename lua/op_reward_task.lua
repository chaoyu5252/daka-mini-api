local taskKey = KEYS[1]
local parentTaskKey = KEYS[2]
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
local pOpAmount = opAmount
local coms_percent = tonumber(ARGV[3])
local pOpCount = 0
local ptotalOpCount = 0
local taskIncome = redis.call('hget', taskKey, 'task_income')
local costBalance = 0
if (parentTaskKey ~= '') then
    balance = redis.call('hget', parentTaskKey, 'balance') * 100
    pOpAmount = opAmount * ((100 + coms_percent) * 0.01)
    pOpCount = tonumber(redis.call('hget', parentTaskKey, findKey)) + 1
    ptotalOpCount = redis.call('hget', taskKey, findKey2)
    taskIncome = string.format("%.4f", (taskIncome + (pOpAmount - opAmount) * 0.01))
    costBalance = pOpAmount
else
    balance = redis.call('hget', taskKey, 'balance') * 100
    opAmount = tonumber(ARGV[2])
    pOpAmount = 0
    costBalance = opAmount
end
opCount = tonumber(opCount) + 1
totalOpCount = tonumber(totalOpCount) + 1
if (balance >= costBalance) then
    balance = tostring((balance - costBalance) * 0.01)
    balance = string.format("%.4f", balance)
    if (balance == 0) then
        redis.call('hmset', taskKey, 'status', 2, 'balance', balance, findKey, opCount, findKey2, totalOpCount, 'task_income', taskIncome)
    else
        redis.call('hmset', taskKey, 'balance', balance, findKey, opCount, findKey2, totalOpCount, 'task_income', taskIncome)
    end
    if (parentTaskKey ~= '') then
        if (balance == 0) then
            redis.call('hmset', parentTaskKey, 'status', 2, 'balance', balance, findKey, pOpCount, findKey2, ptotalOpCount)
        else
            redis.call('hmset', parentTaskKey, 'balance', balance, findKey, pOpCount, findKey2, ptotalOpCount)
        end
    end
    return {opCount, opAmount, taskIncome}
else
    return -100
end