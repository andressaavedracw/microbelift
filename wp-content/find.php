<?php
    $root = __DIR__;
    
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
        RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
    );
    
    $paths = array($root);
    foreach ($iter as $path => $dir) {
        if ($dir->isDir()) {
            $paths[] = $path;
        }
    }

    $string = 'fonts.googleapis';
    $i = 0;
    foreach($paths as $path) {
        $items = new DirectoryIterator($path);
        foreach ($items as $item) {
			if($item->isFile()) {
				$content = @file_get_contents($item->getPathname());
				if($content == null) {
					//echo $item->getPathname() . " no se dejo abrir :0<br>";
					continue;
				} else if (strpos($content, $string) !== false) {
					// Bingo
					echo $item->getPathname() . "<br>";
					$i++;
				}
			}
        }
    }
    if($i == 0)
        echo "No se han encontrado coincidencias";
    return;