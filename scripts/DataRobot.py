#!/usr/bin/python
#coding=utf8

import DataProcessor
import time
from datetime import datetime, timedelta

maxRetry = 5
retry = 0

def start(retry, maxRetry):
        try:
                print "程序开始..."
                while True:
                        curTime = datetime.now()
                        data = DataProcessor.readData()
                        finishTime = datetime.now()
                        delta = finishTime - curTime
                        print "程序执行时间：%s microseconds..." % str(delta.microseconds)
                        if delta.seconds < 1:
                                time.sleep(5)
                        retry = 0
        except Exception, e:
                print "程序异常： %s" % e.message
                if retry < maxRetry:
                        retry += 1
                        print "重试 %d 次" % retry
                        start(retry, maxRetry)
                else :
                        print "%s 重试 %d 次后仍然发生错误，程序退出..." % (curTime, retry)

start(retry, maxRetry)

