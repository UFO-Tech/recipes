<?php

namespace Ufo\Packages;

use Composer\InstalledVersions;

use function file_get_contents;
use function json_decode;
use function str_contains;

class UfoPackage
{
    protected static array $instances = [];

    const string VENDOR_UFO = 'ufo-tech';
    protected const string UNKNOWN = 'unknown';
    const string BUNDLE_NAME = self::UNKNOWN;
    const string UFO_DESCRIPTION = 'UFO-Tech (Universal Flexible Open Technologies) is an initiative aimed at providing PHP developers with tools to create complex yet user-friendly solutions for modern web applications and service-oriented architectures.';

    protected string $bundleName;
    protected ?string $description = null;
    protected array $packages = [];
    protected array $bundles = [];
    protected string $homepage;

    protected array $composerProject = [];
    protected array $composerBundle = [];

    protected string $license;

    protected function __construct(protected string $classFQCN) {
        $this->bundleName = UfoPackage::fromComposer('name') ?? static::BUNDLE_NAME;
        $desc = UfoPackage::fromComposer('description', true);
        $this->description = empty($desc) ? null : $desc;
        $this->parsePackages();
        $this->homepage = UfoPackage::fromComposer('homepage') ?? self::UNKNOWN;
        $this->license = UfoPackage::fromComposer('license', true) ?? 'MIT';
    }

    /**
     * @param class-string $classFQCN
     * @return static
     */
    protected static function getInstance(string $classFQCN): static
    {
        return static::$instances[$classFQCN] ??= new static($classFQCN);
    }

    public static function bundleName(): string
    {
        $self = static::getInstance(static::class);
        return $self->bundleName;
    }

    public static function description(): string
    {
        $self = static::getInstance(static::class);
        return $self->description;
    }

    protected function parsePackages(): void
    {
        if (empty($this->packages)) {
            $this->packages[] = static::VENDOR_UFO . '/packages';
            foreach (require __DIR__.'/../configs/packages.php' as $name => $needRecipe) {
                $name =  str_contains($name, '/')
                    ? $name
                    : static::VENDOR_UFO . '/' . $name;
                $this->packages[] = $name;
                if ($needRecipe) {
                    $this->bundles[] = $name;
                }
            }
        }
    }

    public static function allPackages(): array
    {
        $self = static::getInstance(static::class);
        return $self->packages;
    }

    public static function allBundles(): array
    {
        $self = static::getInstance(static::class);
        return $self->bundles;
    }

    public static function ufoEnvironment(): array
    {
        $self = static::getInstance(static::class);
        $env = [
            'env' => $_ENV['APP_ENV'] ?? '?',
        ];

        foreach ($self->packages as $package) {
            if ($package === $self->bundleName) continue;
            $env = [
                ...$env,
                ...$self->getEnvVersion($package),
            ];
        }
        return $env;
    }

    protected function getEnvVersion(string $name): array
    {
        $res = [];
        if (InstalledVersions::isInstalled($name)) {
            $res = [$name => InstalledVersions::getPrettyVersion($name)];
        }
        return $res;
    }

    public static function version(): string
    {
        $self = static::getInstance(static::class);
        return InstalledVersions::getPrettyVersion($self->bundleName) ?? 'latest';
    }


    public static function bundleDocumentation(): string
    {
        $self = static::getInstance(static::class);
        return $self->homepage;
    }

    public static function projectLicense(): string
    {
        $self = static::getInstance(static::class);
        return $self->license;
    }

    protected function fromComposer(string $key, bool $project = false): mixed
    {
        if (empty($this->composerProject) || empty($this->composerBundle)) {
            $dir = \dirname((new \ReflectionClass($this->classFQCN))->getFileName());
            $dataP = json_decode(file_get_contents($this->projectDir().'/composer.json'), true);
            $dataB = json_decode(file_get_contents($dir.'/../composer.json'), true);
            $this->composerProject = $dataP ?? [];
            $this->composerBundle = $dataB ?? [];
        }

        $pData = $this->composerProject[$key] ?? null;
        $bData = $this->composerBundle[$key] ?? null;

        return $project ? ($pData ?? $bData) : $bData;
    }

    protected function projectDir(): string
    {
        return InstalledVersions::getRootPackage()['install_path'];
    }
}
