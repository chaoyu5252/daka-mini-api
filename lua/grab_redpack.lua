---
--- Created by pangpang.
--- DateTime:       2017/12/20 上午11:48
--- Description:    get amount of one redpack from redpack_dist.
---

local key = KEYS[1]
local ableDistCount = tonumber(redis.call('ZCARD', key))
if ableDistCount > 0 then
    -- get the top redpack distribute amount
    local grabAmount = redis.call('ZRANGE', key, 0, 0)
    if grabAmount then
        -- Remove the posKey
        if redis.call('ZREM', key, grabAmount[1]) then
            return grabAmount[1]
        end
    end
end
return 0

--local key = KEYS[1]
-- how much times to distribute times
--local ableDistCount = tonumber(redis.call('ZCARD', key))
--if ableDistCount > 0 then
    -- get the top redpack distribute amount
--    local posKey = redis.call('ZRANGE', key, 0, 0)
--    if posKey then
--        local grabAmount = redis.call('ZSCORE', key, posKey[1])
--        if grabAmount then
            -- Remove the posKey
--            if redis.call('ZREM', key, posKey[1]) then
--                return grabAmount
--            end
--        end
--    end
--end
--return 0