<?php

namespace Framework;

class ClassExploder  
{
  

   public function __construct()
   {
       $this->scanNamespaces('/Application');
   }

    public static $int = 0;
    
    // Regex minta a #[...] attribútum kereséséhez
    private static string $pattern = '/^\s*\#\s*\[Path\(\'(.*?)\'\)\]/m'; 
    private static string $patternForClass = '/class\s+(\w+)/';
    private static string $patternForNamespace = '/namespace\s+([\w\\\\]+)/';
    private $map;



    private function scanNamespaces($nameSpace) {
        $path = dirname(__DIR__) . $nameSpace;
        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');
        foreach ($phpFiles as $phpFile) {
            $content = file_get_contents($phpFile->getRealPath());
            $matches = [];
            $matchesForClass = [];
            $matchesForNamespaces = [];
            $lastLevelNamespace = "";
            $nameSpace = "";

            if (preg_match(self::$patternForNamespace, $content, $matchesForNamespaces)){
                $nameSpace = $matchesForNamespaces[1];
                $lastLevelNamespace = $matchesForNamespaces[1];
                $namespaceParts = explode('\\', $lastLevelNamespace);
                $lastLevelNamespace = end($namespaceParts);
            }

            if (preg_match(self::$patternForClass, $content, $matchesForClass)) {
                $className = $matchesForClass[1]; // Az osztály neve
                if (preg_match(self::$pattern, $content, $matches)) {
                    $attribute = $matches[1];
                    if (!str_starts_with($attribute, "/")){
                        $attribute = "/".$attribute;
                    }
                    $this->map[$attribute] = [ 'className'=>$className, 'nameSpace'=>$nameSpace ];
                }
            }
        }
    }

    public function get_controller_mapping() {
        return $this->map;
    }
}