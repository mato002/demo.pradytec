<?php
	
	ini_set("memory_limit",-1);
    ignore_user_abort(true);
    set_time_limit(300);
	
	require "functions.php";
	
	function backup(){
		date_default_timezone_set("Africa/Nairobi");
		$db = new DBO(); $cid = CLIENT_ID;
		$tables1=$tables2=$tables3=$zipfiles=[];
		
		$res = $db->query(1,"SHOW TABLES");
		foreach($res as $row){ $tables1[]=$row["Tables_in_".DATABASES[1]['name']]; }
		
		$res = $db->query(2,"SHOW TABLES");
		foreach($res as $row){ $tables2[]=$row["Tables_in_".DATABASES[2]['name']]; }
		
		$res = $db->query(3,"SHOW TABLES");
		foreach($res as $row){ $tables3[]=$row["Tables_in_".DATABASES[3]['name']]; }
		$dbs = array(1=>$tables1,2=>$tables2,3=>$tables3);
			
		foreach($dbs as $key=>$tbls){
			$dbname = DATABASES[$key]['name'];
			$data = "\n-- Database: `$dbname`\n\n";
				
			foreach($tbls as $tbl){
				$con = $db->mysqlcon($key);
				$qri = mysqli_query($con,"SELECT *FROM `$tbl`");
				$numfields = mysqli_num_fields($qri);
				$all = mysqli_num_rows($qri);
				if(mysqli_error($con)){
					file_put_contents("atm.txt",mysqli_error($con)."\n",FILE_APPEND);
				}
				
				$data.= "-- Table: `$tbl`\n\n";
				$sql = mysqli_fetch_row(mysqli_query($con,"SHOW CREATE TABLE `$tbl`"));
				$data.= "\n\n".$sql[1].";\n\n"; $no=0; $k=200;
				
				while($row=mysqli_fetch_row($qri)){
					$no++; $k+=($no==$k) ? 300:0;
					$data.=(($k-1)==$no or $no==1) ? "INSERT INTO `$tbl` VALUES\n(":"(";
					for($j=0; $j<$numfields; $j++){
						if(isset($row[$j])){ $data.="'".addslashes(stripslashes($row[$j]))."'"; }
						else{ $data.="''"; }
						$data.=($j<$numfields-1) ? ",":"";
					}
					$data.=($no==$all or $no==($k-2)) ? ");\n":"),\n";
				}
				
				$data.="\n-- --------------------------------------------------------------------------\n";
				mysqli_close($con);
			}
			
			$handle = fopen("$dbname.sql","w+");
			fwrite($handle,$data);
			fclose($handle);
			$zipfiles[] = "$dbname.sql";
		}
		
		$dir = str_replace(array("\core","/core"),"",__DIR__ . DIRECTORY_SEPARATOR);
		$exclude = array("zip","log","sql","xlsx","csv"); 
		
		$folders = array("c2b","mfs","mfs/accounts","mfs/bi","mfs/dbsave","mfs/hr","docs","core","pdf/files","xls"); 
		foreach($folders as $folder){
			$files = []; $fname = "$dir$folder/";
			$zip = (@array_pop(explode(".",$fname))=="com") ? @array_pop(explode("/",$fname)).".zip":str_replace("/","-",$folder).".zip";
			
			foreach(getfiles($fname) as $file){
				if(!in_array(@array_pop(explode(".",$file)),$exclude) && substr($file,0,1)!="."){
					$files[]=($zip==$_SERVER['HTTP_HOST'].".zip") ? $file:"$fname/$file"; 
				}
			}
			
			if(count($files)){
				addtoZip($zip,$files); $zipfiles[]=$zip;
			}
		}
		
		$filename = date("F-d-Y-Hi")."-backup.zip";
		addtoZip($filename,$zipfiles);
		foreach($zipfiles as $file){
			unlink($file);
		}
		
		return $filename;
	}
	
	if(isset($_GET['backup'])){
		$backup = backup();  
		$backups = array($backup);
		
		foreach(getfiles(getcwd()) as $file){
			if(@array_pop(explode("-",$file))=="backup.zip" && $file!=$backup){
				$backups[]=$file;
			}
		}
		
		$res = upload(BACKUP_URL,$backups);
		if($res=="success"){
			foreach($backups as $back){
				unlink($back);
			}
			echo implode(",",$backups)." uploaded successfull";
		}
	}
	
?>