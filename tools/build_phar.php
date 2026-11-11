<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$buildDir = $root . DIRECTORY_SEPARATOR . "build";
$target = $buildDir . DIRECTORY_SEPARATOR . "VivaPet.phar";

if((int) ini_get("phar.readonly") !== 0){
    fwrite(STDERR, "Run with: php -d phar.readonly=0 tools/build_phar.php\n");
    exit(1);
}
if(!is_dir($buildDir) && !mkdir($buildDir, 0775, true) && !is_dir($buildDir)){
    throw new RuntimeException("Could not create build directory");
}
if(is_file($target) && !unlink($target)){
    throw new RuntimeException("Could not replace existing PHAR");
}
$required=["plugin.yml","resources/config.yml","src/Main.php","src/PetManager.php","src/entity/PetEntity.php","src/entity/PetTypes.php","src/listener/PlayerListener.php"];
foreach($required as $relative){
    if(!is_file($root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative))){
        throw new RuntimeException("Missing required file: " . $relative);
    }
}
$phar=new Phar($target,0,"VivaPet.phar");
$phar->startBuffering();
$files=["plugin.yml","resources/config.yml","LICENSE"];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . "src",FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){
    if($file->isFile() && str_ends_with(strtolower($file->getFilename()),".php")){
        $files[]=substr($file->getPathname(),strlen($root)+1);
    }
}
sort($files,SORT_STRING);
foreach(array_unique($files) as $relative){
    $path=$root . DIRECTORY_SEPARATOR . str_replace("/",DIRECTORY_SEPARATOR,$relative);
    if(is_file($path)) $phar->addFile($path,str_replace(DIRECTORY_SEPARATOR,"/",$relative));
}
$phar->setStub("<?php __HALT_COMPILER();");
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->stopBuffering();
$check=new Phar($target);
foreach(["plugin.yml","resources/config.yml","src/Main.php","src/PetManager.php","src/entity/PetTypes.php"] as $file){
    if(!$check->offsetExists($file)) throw new RuntimeException("PHAR is missing " . $file);
}
printf("Built %s (%d bytes)\n",$target,filesize($target));
