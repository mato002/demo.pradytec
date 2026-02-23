<?php
	
	require 'vendor/autoload.php';

	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
	
	function genExcel($data,$fromcell,$widths,$fname,$desc,$pass,$hrows=0){
		if(!is_dir("docs")){ mkdir("docs",0777,true); }
		foreach(glob("docs/*") as $file){
			if((time()-filemtime($file))>60){ unlink($file); }
		}
		
		$header = array(
		'font'  => array(
			'bold'  => true,
			'color' => array('rgb' => '191970'),
			'size'  => 12,
			'name'  => 'cambria'),
			'borders' => array(
				'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,]
			),
			'fill' => [
				'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
				'rotation' => 90,
				'startColor' => ['argb' => 'FFFFFFFF'],
				'endColor' => ['argb' => 'FFA0A0A0']
			]
		);
		
		$title = array('font'  => array('bold' =>true,'color' => array('rgb' => '008fff'),'size'  => 13,'name'  => 'cambria'),
		'borders' => array('top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,]));
		$count = ($hrows) ? $hrows:count($data[1])+2;
		
		$extra = ["A","B","C","D","E","F","G","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","AA","AB","AC","AD","AE","AF","AG","AH","AI","AJ","AK","AL","AM","AN"];
		$tocell = $extra[$count].substr($fromcell,1); $from2 = substr($fromcell,0,1).(substr($fromcell,1)+1); 
		$tocell2 = substr($tocell,0,strlen($tocell)-1).(substr($tocell,strlen($tocell)-1)+1);
		
		$spreadsheet = new Spreadsheet();
		$spreadsheet->getProperties()->setCreator("Pradytech MFI System")->setTitle($desc)->setLastModifiedBy("Pradytech MFI System")->setDescription($desc);
		$spreadsheet->getActiveSheet()->fromArray( $data, NULL, $fromcell )->getStyle($fromcell.':'.$tocell)->applyFromArray($title);
		$spreadsheet->getActiveSheet()->getStyle($from2.':'.$tocell2)->applyFromArray($header);
		
		foreach($widths as $key=>$width){
			$cell = $extra[array_search(substr($fromcell,0,1),$extra)+$key];
			$spreadsheet->getActiveSheet()->getColumnDimension($cell)->setWidth($width);
		}
	
		if($pass){
			$spreadsheet->getActiveSheet()->getProtection()->setSheet(true);
			$security = $spreadsheet->getSecurity();
			$security->setLockWindows(true);
			$security->setLockStructure(true);
			$security->setWorkbookPassword($pass);
		}
		
		$writer = new Xlsx($spreadsheet);
		$writer->save($fname);
		return 1;
	}
	
	function openExcel($file){
		$ext = @array_pop(explode(".",$file));
		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(ucfirst($ext));
		$reader->setLoadAllSheets();
		$reader->setReadDataOnly(true);
		$spreadsheet = $reader->load($file);
		$worksheet = $spreadsheet->getActiveSheet();
		$data = [];
		
		foreach ($worksheet->getRowIterator() as $row){
			$rows = [];
			$cellIterator = $row->getCellIterator();
			$cellIterator->setIterateOnlyExistingCells(true);
			foreach ($cellIterator as $cell){
				$rows[]=$cell->getValue();
			}
			if(count($rows)){ $data[]=$rows; }
		}
		
		return $data;
	}
	
	function exceldate($val){
		$UNIX_DATE = ($val - 25569) * 86400;
		return gmdate("d-m-Y", $UNIX_DATE);
	}

?>