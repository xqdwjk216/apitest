<?php

	//收集日志（基于管道处理），可以指定单个或多个用例更新

	if( empty($argv[1]) ){
		die("Usage php LogParser.php {config}.json [-[ac]case_name]\n");
	}

	$config = file_get_contents($argv[1]);
	$config = preg_replace("/(?<!\S)\/\/.+[\n-\r]/","",$config);
	$config = preg_replace("/,\s+\}/","}",$config);
	$configArr = json_decode($config,true);
	
	if( !$configArr ){
		die("json parse error\n");
	}

	//获取需要更新的atom或者case
	$len = count($argv);
	$atomArr = [];
	$caseArr = [];

	if( $len > 2 ){
		for( $i = 2 ; $i < $len ; $i++ ){

			$cmd = $argv[$i];
			$prefix = substr($cmd,0,2);
			$module_name = substr($cmd,2);

			if( !$module_name ){
				continue;
			}

			if( $prefix == '-a' ){
				$atomArr[] = $module_name;
			}elseif( $prefix == '-c' ){
				$caseArr[] = $module_name;
			}
		}
	}

	$handles = [];
	$module_names = [];
	$fail_arr = [];
	foreach( $configArr['atom'] as $index => $config ){
		$need_handle = empty($atomArr);
		$atom = "";
		if( !$need_handle ){
			foreach( $atomArr as $atom ){
				$sampleUrl = $config['sampleUrl'];
				if( strpos($sampleUrl,"_m=".$atom) === 0 ){
					$need_handle = true;
				}
			}
		}

		if( $need_handle ){	//需要处理
			if( empty($atom) ){
				$atom = preg_replace("/_m=(.+?)&.+/","$1",$config['sampleUrl']);
			}
			$handles[$atom] = $config;
			$handles[$atom]['map'] = &$configArr['atom'][$index];
			$module_names[$atom] = $atom;
		}
	}

	$a = &$configArr['atom'];
	$c = &$configArr['case'];
	$fr=fopen("php://stdin","r");
	$done_count = 0;
	$succ_modules = [];

	while( !feof($fr) ){
	    $in = fgets($fr,1024);
	    $in = trim($in);
	    if( !$in ){
	        continue;
	    }

	    $line = preg_replace("/.+?(_m=.+\}).+/","$1",$in);
	    $module_name = preg_replace("/_m=(.+?)&.+/","$1",$line);
	    if( in_array($module_name,$module_names) ){
	    	$succ_modules[$module_name] = $module_name;
	    	$handler = &$handles[$module_name];
	    	if( empty($handler['handled']) ){	//只处理一次
		    	$handler['handled'] = true;
		    	$done_count++;
		    	//只处理args
		    	$args = json_decode(preg_replace("/.+?args=(.+)/","$1",$line),true);
		    	if( !empty($args) ){
		    		foreach( $args as $k => $v ){
		    			if( is_object($v) || is_array($v) ){
		    				$v = json_encode($v,JSON_UNESCAPED_UNICODE);
		    			}

	    				$handler['map']['args'][$k] = $v;
		    		}
		    	}
		    }
		}
	}

	echo json_encode($configArr,JSON_UNESCAPED_UNICODE);
	exit;
	echo "done|".$done_count."/".count($configArr['atom']),"\n";
	echo "fail|".implode(",",array_diff($module_names,$succ_modules));
	exit;

	fclose ($fr);
	exit;