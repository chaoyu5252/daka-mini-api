#!/usr/bin/python
#coding=utf8

import httplib
import time
import md5

def readData():
        httpClient = None
        try:
		timestamp = int(time.time())
                #param = "timestamp=%sE82EAB79-C1B4-883B-5737-13437911A637" % timestamp
                #newMd5 = md5.new()
                #newMd5.update(param)
                #sign = newMd5.hexdigest()
                httpClient = httplib.HTTPConnection('127.0.0.1', 8888, timeout=30)
                #httpClient.request('GET', '/_API/_server/_updatePetCap?timestamp=%s&sign=%s' % (timestamp, sign))
                httpClient.request('GET', '/_API/_returnRedPacket')
                response = httpClient.getresponse()
                if response.status == 200 :
                        return response.read()
        except Exception, e:
                print e
        finally:
                if httpClient:
                        httpClient.close()
