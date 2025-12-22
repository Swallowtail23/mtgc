<?php

use PHPUnit\Framework\TestCase;

class SrcClassMapTest extends TestCase
{
    public function testSrcClassesMatchFileNamesAndNamespaces()
    {
        $srcRoot = __DIR__ . '/../src/MTG';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot));
        $mismatches = [];

        $stubbed = [
            'MTG\\Auth\\SessionManager',
            'MTG\\Auth\\TrustedDeviceManager',
            'MTG\\Auth\\TwoFactorManager',
            'MTG\\Auth\\UserStatus',
            'MTG\\Auth\\PasswordCheck',
            'MTG\\Cards\\PriceManager',
            'MTG\\Core\\INI'
        ];

        foreach ($iterator as $file) :
            if (!$file->isFile() || $file->getExtension() !== 'php') :
                continue;
            endif;

            $definition = $this->getClassDefinition($file->getPathname());
            if ($definition === null) :
                continue;
            endif;

            $fileName = $file->getBasename('.php');
            if ($definition['class'] !== $fileName) :
                $mismatches[] = $file->getPathname() . ' => class ' . $definition['class'];
            endif;

            $relative = str_replace($srcRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $expectedNamespace = 'MTG\\' . str_replace(
                DIRECTORY_SEPARATOR,
                '\\',
                dirname($relative)
            );
            if ($expectedNamespace === 'MTG\\.') :
                $expectedNamespace = 'MTG';
            endif;
            if ($definition['namespace'] !== $expectedNamespace) :
                $mismatches[] = $file->getPathname()
                    . ' => namespace ' . ($definition['namespace'] ?? 'none')
                    . ' (expected ' . $expectedNamespace . ')';
            endif;
        endforeach;

        $this->assertSame([], $mismatches, 'Class/file mismatches: ' . implode(', ', $mismatches));
    }

    public function testSrcClassesAreAutoloadable()
    {
        $srcRoot = __DIR__ . '/../src/MTG';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot));
        $mismatches = [];
        $stubbed = [
            'MTG\\Auth\\SessionManager',
            'MTG\\Auth\\TrustedDeviceManager',
            'MTG\\Auth\\TwoFactorManager',
            'MTG\\Auth\\UserStatus',
            'MTG\\Auth\\PasswordCheck',
            'MTG\\Cards\\PriceManager',
            'MTG\\Core\\INI'
        ];

        foreach ($iterator as $file) :
            if (!$file->isFile() || $file->getExtension() !== 'php') :
                continue;
            endif;

            $definition = $this->getClassDefinition($file->getPathname());
            if ($definition === null) :
                continue;
            endif;

            $fqcn = $definition['namespace']
                ? $definition['namespace'] . '\\' . $definition['class']
                : $definition['class'];

            if (in_array($fqcn, $stubbed, true)) :
                continue;
            endif;

            if (!class_exists($fqcn)) :
                $mismatches[] = $file->getPathname() . ' => class not autoloadable (' . $fqcn . ')';
                continue;
            endif;

            $reflection = new ReflectionClass($fqcn);
            if (realpath($reflection->getFileName()) !== realpath($file->getPathname())) :
                $mismatches[] = $file->getPathname() . ' => autoload mismatch (' . $fqcn . ')';
            endif;
        endforeach;

        $this->assertSame([], $mismatches, 'Autoload mismatches: ' . implode(', ', $mismatches));
    }

    private function getClassDefinition($path)
    {
        $tokens = token_get_all(file_get_contents($path));
        $count = count($tokens);
        $namespace = null;
        $className = null;

        for ($i = 0; $i < $count; $i++) :
            if (!is_array($tokens[$i])) :
                continue;
            endif;

            if ($tokens[$i][0] === T_NAMESPACE) :
                for ($j = $i + 1; $j < $count; $j++) :
                    if (!is_array($tokens[$j])) :
                        if ($tokens[$j] === ';') :
                            break;
                        endif;
                        continue;
                    endif;
                    if (
                        $tokens[$j][0] === T_NAME_QUALIFIED
                        || $tokens[$j][0] === T_NAME_FULLY_QUALIFIED
                    ) :
                        $namespace = ltrim($tokens[$j][1], '\\');
                        break;
                    endif;
                    if ($tokens[$j][0] === T_STRING) :
                        $namespace = $tokens[$j][1];
                        break;
                    endif;
                endfor;
            endif;

            if ($tokens[$i][0] === T_CLASS) :
                for ($j = $i + 1; $j < $count; $j++) :
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) :
                        $className = $tokens[$j][1];
                        break 2;
                    endif;
                endfor;
            endif;
        endfor;

        if ($className === null) :
            return null;
        endif;

        return [
            'class' => $className,
            'namespace' => $namespace
        ];
    }
}
