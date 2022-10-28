<?php

namespace EvanG\Modules\MailSystem\Helpers;

use EvanG\Modules\MailSystem\RequestHandler;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
use Psr\Http\Message\ResponseInterface;

class Images
{
    public static function getImageDataUrl(?MediaFile $media_file): ?string
    {
        $response = self::getImageDataResponse($media_file);
        return $response == null ? null : 'data: ' . $media_file->mimeType() . ';base64,' . base64_encode((string)$response->getBody());
    }

    public static function getImageDataResponse(?MediaFile $media_file): ?ResponseInterface
    {
        if ($media_file == null) return null;

        $image_factory = Registry::imageFactory();
        return $image_factory->mediaFileThumbnailResponse(
            $media_file,
            50,
            50,
            "crop",
            false
        );
    }

    public static function getImageDirectUrl(Individual $individual): ?string
    {
        return self::getIndividualPicture($individual) == null ? null : route(RequestHandler::class, ["action" => "image", "xref" => $individual->xref(), "tree" => $individual->tree()->id()]);
    }

    public static function getIndividualPicture(Individual $individual): ?MediaFile
    {
        foreach ($individual->facts(['OBJE']) as $fact) {
            $media_object = $fact->target();
            if ($media_object instanceof Media) {
                $media_file = $media_object->firstImageFile();
                if ($media_file instanceof MediaFile) return $media_file;
            }
        }
        return null;
    }
}