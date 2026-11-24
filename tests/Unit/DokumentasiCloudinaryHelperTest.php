<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\DokumentasiController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DokumentasiCloudinaryHelperTest extends TestCase
{
    private DokumentasiController $controller;

    private \ReflectionMethod $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new DokumentasiController();

        $reflection = new ReflectionClass(DokumentasiController::class);

        $this->helper = $reflection->getMethod('extractCloudinaryPublicId');
        $this->helper->setAccessible(true);
    }

    private function extract(string $url): ?string
    {
        return $this->helper->invoke($this->controller, $url);
    }

    public function test_extracts_image_public_id_with_version_and_extension(): void
    {
        $url = 'https://res.cloudinary.com/qwto0skl/image/upload/v1795527259/pad/dokumentasi/covers/sample_cover.png';

        $this->assertSame(
            'pad/dokumentasi/covers/sample_cover',
            $this->extract($url)
        );
    }

    public function test_extracts_video_public_id_with_version_and_extension(): void
    {
        $url = 'https://res.cloudinary.com/qwto0skl/video/upload/v1795527259/pad/dokumentasi/media/sample_video.mp4';

        $this->assertSame(
            'pad/dokumentasi/media/sample_video',
            $this->extract($url)
        );
    }

    public function test_extracts_public_id_without_version(): void
    {
        $url = 'https://res.cloudinary.com/qwto0skl/image/upload/pad/dokumentasi/covers/no_version_cover.jpg';

        $this->assertSame(
            'pad/dokumentasi/covers/no_version_cover',
            $this->extract($url)
        );
    }

    public function test_extracts_public_id_without_extension(): void
    {
        $url = 'https://res.cloudinary.com/qwto0skl/image/upload/v1795527259/pad/dokumentasi/covers/no_ext_cover';

        $this->assertSame(
            'pad/dokumentasi/covers/no_ext_cover',
            $this->extract($url)
        );
    }

    public function test_returns_null_for_non_cloudinary_host(): void
    {
        $url = 'https://attacker.com/res.cloudinary.com/image/upload/v12345/pad/test.jpg';

        $this->assertNull($this->extract($url));
    }

    public function test_returns_null_for_local_storage_path(): void
    {
        $url = '/storage/dokumentasi/covers/local_file.jpg';

        $this->assertNull($this->extract($url));
    }

    public function test_returns_null_for_invalid_url(): void
    {
        $url = 'not-a-valid-url';

        $this->assertNull($this->extract($url));
    }
}
