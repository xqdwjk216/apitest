# apitest
It's a tool to test your api automatically.
Api are devided into 2 parts.First is atom api,which is not based other api.The other is case api,whichi is based on one or more apis' input or output.   
Api test case is defined in TestCaseDemo.json   
You can gather your TestCase from access.log(nginx,apache,etc) with LogParse.php
```
tail -n 10 /var/log/demo888.cn.access.log | php LogParser.php TestCaseDemo.json -aroute_a > TestCaseDemo.route_a.json
```
Command above means I want to fetch args from /var/log/demo888.cn.access.log and assemble route_a into a new TestCase named TestCaseDemo.route_a.json.By the way,param -a is optional.Once it's omitted,all the atom api will be matched from access_log.   
TestCaseDemo.route_a.json may look like
```
{"url":"http:\/\/www.demo888.cn\/index.php?","args":{"tel":"xxxxxxxxxxx","token":"d783715c147b3da0e1bde22173be115d","_t":"<?php time(); ?>"},"atom":[{"desc":"this is test for route_a","sampleUrl":"_m=route_a&args={\"input_str\":${input_str},\"input_time\":${input_time}}","args":{"input_str":"this is a","input_time":"<?php time() ?>"}}],"case":{"route_b":[{"route_a":{"desc":"this is route_a","args":{"input_str":"this is a","input_time":"<?php 'timestamp:'.time() ?>"}}},{"route_b":{"desc":"this is route_b","args":{"str":"${route_a['output']['data']['output_str']}","_t":"${route_a['args']['input_time']}"}}}]}}
```
Then you can execute TestCaseDemo.route_a.json to figure out a result
```
php TestCase.php TestCaseDemo.route_a.json
```
You will get something like this
```
http://www.demo888.cn/index.php?_m=route_a&args={"input_str":"this is a","input_time":1481731955}&tel=xxxxxxxxxxx&token=d783715c147b3da0e1bde22173be115d&_t=1481731955   
--------------------------------------------------
{"ret":0,"msg":"","data":{"echo":"this is route a","output_str":"this is a","output_time":1481731955}}   
---------------------------------------------------------------
```
##Next may be something interesting
As mentioned above,Case Api is defined as Group of Atom Apis.We just need to figure out their relationship among input and output.   
Like this definition in TestCaseDemo.2016.12.14.json,route_b is based on route_a's output_str and route_a's input_time   
```
"case": {
		"route_b": [{
			"route_a": {
				"desc": "this is route_a",
				"args": {
					"input_str": "this is input for route_a",
					"input_time": "<?php time() ?>"
				}
			}
		}, {
			"route_b": {
				"desc": "this is route_b",
				"args": {
					"input_str": "${route_a['output']['data']['output_str']}",
					"input_time": "${route_a['args']['input_time']}"
				}
			}
		}]
	}
```
If we execute this
```
php TestCase.php TestCaseDemo.2016.12.14.json -croute_b
```
Then we get
```
http://www.demo888.cn/index.php?_m=route_a&args={"input_str":"this is input for route_a","input_time":1481732294}&tel=xxxxxxxxxxx&token=d783715c147b3da0e1bde22173be115d&_t=1481732294
--------------------------------------------------
{"ret":0,"msg":"","data":{"echo":"this is route a","output_str":"this is input for route_a","output_time":1481732294}}
----------------------------------------------------------------------------------------------------
http://www.demo888.cn/index.php?_m=route_b&args={"input_str":"this is input for route_a","input_time":1481732294}&tel=xxxxxxxxxxx&token=d783715c147b3da0e1bde22173be115d&_t=1481732294
--------------------------------------------------
{"ret":0,"msg":"","data":{"echo":"this is route b","output_str":"this is input for route_a","output_time":1481732294}}
----------------------------------------------------------------------------------------------------
```
See that?Route_b assembled args from route_a's output_str and input_time.If we orgnize api(both atom and case) in this way.We can easily run all our api at once.Which may be an effective
way to deduct bugs^_^.   
As you see,the route and args are customized.If you want to test your project in this way,you should change some code.
Your interest or feedback would encorage me.Any opnion or suggest,please mail to xqdwjk216@gmail.com.Thks
