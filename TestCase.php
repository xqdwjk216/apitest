<?php
	if( empty($argv[1]) ){
		die("Usage php TestCase.php TestCase.json [-[ac]case_name]\n");
	}

	$config = file_get_contents($argv[1]);
	$config = preg_replace("/(?<!\S)\/\/.+[\n-\r]/","",$config);
	$config = preg_replace("/,\s+\}/","}",$config);
	$configArr = json_decode($config,true);
	
	if( !$configArr ){
		die("json parse error\n");
	}

	$len = count($argv);
	$atomArr = [];
	$caseArr = [];

	function __eval($input)
	{

		$input = trim($input);
		$output = "";
		if( strpos($input,"<?php ") === 0 ){
			$output = eval("return ".mb_substr($input,5,mb_strlen($input)-3));
		}else{
			$output = $input;
		}

		return $output;
	}

	function __jsonFormat($json)
	{
		$tabcount = 0;
		$result = '';
		$inquote = false;
		$ignorenext = false;
		$tab = "\t";
		$newline = "\n";

		for($i = 0; $i < strlen($json); $i++) {
		$char = $json[$i];
		if ($ignorenext) {
			$result .= $char;
			$ignorenext = false;
		} else {
			switch($char) {
				case '{':
					$tabcount++;
					$result .= $char . $newline . str_repeat($tab, $tabcount);
				break;
				case '}':
					$tabcount--;
					$result = trim($result) . $newline . str_repeat($tab, $tabcount) . $char;
				break;
				case ',':
					$result .= $char . $newline . str_repeat($tab, $tabcount);
				break;
				case '"':
					$inquote = !$inquote;
					$result .= $char;
				break;
				case '\\':
					if ($inquote) $ignorenext = true;
					$result .= $char;
				break;
				default:
					$result .= $char;
				}
			}
		}
		return $result;
	}

	if( $len > 2 ){
		for( $i = 2 ; $i < $len ; $i++ ){

			$cmd = $argv[$i];
			$prefix = substr($cmd,0,2);
			$module_name = substr($cmd,2);

			if( !$module_name ){
				continue;
			}

			$allAtomArr[$module_name] = $module_name;
			if( $prefix == '-a' ){
				$atomArr[] = $module_name;
			}elseif( $prefix == '-c' ){
				$caseArr[] = $module_name;
			}
		}
	}

	// if( empty($atomArr) && empty($caseArr) ){
	// 	die("Usage php TestCase.php TestCase.json [-[ac]case_name]\n");
	// }

	$_args = [];
	$base_url = $configArr['url'];

	//base参数处理
	foreach( $configArr['args'] as $key => $val ){
		$_args[$key] = __eval($val);
	}

	$succ_arr = [];
	$fail_arr = [];

	function atom_parse($config_item)
	{
		global $allAtomArr,$_args,$base_url;
		$sampleUrl = $config_item['sampleUrl'];
		$config_item['url'] = '';
		$config_item['module'] = preg_replace("/_m=(.+?)&.+/", "$1", $sampleUrl);

		$url = $sampleUrl."&".http_build_query($_args);
		//检查是否有定制化参数
		if( !empty($config_item['args']) ){	//需要填充参数
			foreach( $config_item['args'] as $k => $v ){
				$v = __eval($v);
				if( !is_int($v) && strpos($v, "{") !== 0 ){
					$v = '"'.$v.'"';
				}
				$url = str_replace('${'.$k.'}',$v,$url);
			}
		}

		//去掉多余的参数
		$url = $base_url.$url;
		$config_item['url'] = $url;
		$allAtomArr[$config_item['module']] = $config_item;
	
		return $config_item;
	}

	//处理所有的原子接口
	foreach( $configArr['atom'] as $atom_item ){
		$atom_item = atom_parse($atom_item);
		$allAtomArr[$atom_item['module']] = $atom_item;
		
		if( (empty($atomArr) && empty($caseArr)) || in_array($atom_item['module'],$atomArr) ){
			$url = $atom_item['url'];
			echo $url,"\n",str_repeat("-", 50),"\n";
			$str = file_get_contents($url);
			echo $str,str_repeat("-", 100),"\n";
		}

	}

	if( !empty($caseArr) ){

		foreach( $caseArr as $case_name ){

			$caseStack = [];	//用例执行队列

			if( empty($configArr['case'][$case_name] ) ){
				die("case not defined:".$case_name."\n");
			}

			$case = $configArr['case'][$case_name];

			foreach( $case as $case_arr ){	

				foreach( $case_arr as $atom_module_name => $case_atom ){//对每个原子接口进行处理

					if( empty($allAtomArr[$atom_module_name]) ){
						die("Atom interface not definded:".$atom_module_name."\n");
					}

					$atom = $allAtomArr[$atom_module_name];	//已经解析好的原子接口
					$url = $atom['url'];	//实际请求地址
					if( !empty($case_atom['args']) ){	//需要定制化参数
						$sampleUrl = $atom['sampleUrl'];
						foreach( $case_atom['args'] as $k => $v ){
							
							$v = trim($v);
							if( strpos($v,"\${") === 0 ){

								$prev_atom_name = substr($v, 2,stripos($v, "[")-2);
								$stack = &$caseStack[$prev_atom_name];

								$cnt = count($stack['output']['data']);
								$v = str_replace("__COUNT__",$cnt,$v);

								$pos = stripos($v, "[");
								$surffix = substr($v,$pos,strlen($v)-$pos-1);
								
								if( empty($caseStack[$prev_atom_name]) ){
									die("case element not found:".$prev_atom_name."\n");
								}

								$eval = 'return $stack'.$surffix.";";
								$v = eval($eval);
								$v = __eval($v);
							}else{
								$v = __eval($v);
							}

							if( !is_int($v) && strpos($v, "{") !== 0 ){
								$v = '"'.$v.'"';
							}
							$sampleUrl = str_replace('${'.$k.'}',$v,$sampleUrl);
						}

						$url = $base_url . $sampleUrl."&".http_build_query($_args);
					}

					echo $url,"\n",str_repeat("-", 50),"\n";
					$str = file_get_contents($url);
					echo $str,"\n";
					$output = json_decode($str,true);
					echo str_repeat("-", 100),"\n";

					$atom['output'] = [];
					$atom['output']['ret'] = isset($output['ret']) ? $output['ret'] : 0;
					$atom['output']['msg'] = empty($output['msg']) ? "" : $output['msg'];
					if( !empty($output['data']) ){
						foreach( $output['data'] as $val ){
							if( is_array($val) ){
								$atom['output']['data'] = $val;
								break;
							}
						}

						if( empty($atom['output']['data']) ){
							$atom['output']['data'] = $output['data'];
						}
					}

					$caseStack[$atom_module_name] = $atom;
				
				}

			}
		}

	}

	if( !empty($fail_arr) ){
		die("Fail|".count($fail_arr)."|".implode(",",$fail_arr)."\n");
	}
