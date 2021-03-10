<?php

namespace wcf\system\background\job;

use BadMethodCallException;
use GuzzleHttp\Psr7\Response;
use wcf\data\unfurl\url\UnfurlUrl;
use wcf\data\unfurl\url\UnfurlUrlAction;
use wcf\system\message\unfurl\exception\DownloadFailed;
use wcf\system\message\unfurl\exception\ParsingFailed;
use wcf\system\message\unfurl\exception\UrlInaccessible;
use wcf\system\message\unfurl\UnfurlResponse;
use wcf\system\WCF;
use wcf\util\FileUtil;
use wcf\util\StringUtil;
use wcf\util\Url;

/**
 * Represents a background job to get information for an url.
 *
 * @author      Joshua Ruesweg
 * @copyright   2001-2020 WoltLab GmbH
 * @license     GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package     WoltLabSuite\Core\System\Background\Job
 * @since       5.4
 */
final class UnfurlUrlBackgroundJob extends AbstractBackgroundJob
{
    /**
     * @var int
     */
    private $urlID;

    /**
     * UnfurlURLJob constructor.
     *
     * @param UnfurlUrl $url
     */
    public function __construct(UnfurlUrl $url)
    {
        $this->urlID = $url->urlID;
    }

    /**
     * @inheritDoc
     */
    public function retryAfter()
    {
        switch ($this->getFailures()) {
            case 1:
                // 5 minutes
                return 5 * 60;
            case 2:
                // 30 minutes
                return 30 * 60;
            case 3:
                // 2 hours
                return 2 * 60 * 60;
        }
    }

    /**
     * @inheritDoc
     */
    public function perform()
    {
        $unfurlUrl = new UnfurlUrl($this->urlID);

        try {
            $unfurlResponse = UnfurlResponse::fetch($unfurlUrl->url);

            if (empty(StringUtil::trim($unfurlResponse->getTitle()))) {
                $this->save(UnfurlUrl::STATUS_REJECTED);

                return;
            }

            $title = StringUtil::truncate(StringUtil::trim($unfurlResponse->getTitle()), 255);
            $description = "";
            if ($unfurlResponse->getDescription()) {
                $description = StringUtil::truncate(StringUtil::trim($unfurlResponse->getDescription()), 160);
            }

            $imageData = [];
            $imageID = null;
            if ($unfurlResponse->getImageUrl()) {
                $imageUrl = StringUtil::trim($unfurlResponse->getImageUrl());

                if (Url::is($imageUrl)) {
                    $imageID = self::getImageIdByUrl($unfurlResponse->getImageUrl());

                    if ($imageID === null) {
                        $imageData = $this->getImageData($unfurlResponse);
                    }
                }
            }

            $this->save(
                UnfurlUrl::STATUS_SUCCESSFUL,
                $title,
                $description,
                $imageID,
                $imageData
            );
        } catch (UrlInaccessible | ParsingFailed $e) {
            if (\ENABLE_DEBUG_MODE) {
                \wcf\functions\exception\logThrowable($e);
            }

            $this->save(UnfurlUrl::STATUS_REJECTED);
        }
    }

    private function getImageData(UnfurlResponse $unfurlResponse): array
    {
        $imageSaveData = [];

        if (empty($unfurlResponse->getImageUrl()) || !Url::is($unfurlResponse->getImageUrl())) {
            throw new BadMethodCallException("Invalid image given.");
        }

        try {
            $imageResponse = $unfurlResponse->getImage();
            $image = $this->downloadImage($imageResponse);
            $imageData = \getimagesizefromstring($image);

            if ($imageData !== false) {
                if ($this->validateImage($imageData)) {
                    $imageSaveData['imageUrl'] = StringUtil::trim($unfurlResponse->getImageUrl());
                    $imageSaveData['width'] = $imageData[0];
                    $imageSaveData['height'] = $imageData[1];
                    if (!(MODULE_IMAGE_PROXY || IMAGE_ALLOW_EXTERNAL_SOURCE)) {
                        $imageSaveData['imageHash'] = $this->saveImage($imageData, $image);
                        $imageSaveData['imageExtension'] = $this->getImageExtension($imageData);
                    }
                }
            }
        } catch (UrlInaccessible | DownloadFailed $e) {
            $imageSaveData = [];
        }

        return $imageSaveData;
    }

    private static function getImageIdByUrl(string $url): ?int
    {
        $sql = "SELECT  imageID
                FROM    wcf" . WCF_N . "_unfurl_url_image
                WHERE   imageUrl = ?";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute([$url]);

        $imageID = $statement->fetchSingleColumn();

        if ($imageID === false) {
            return null;
        }

        return $imageID;
    }

    private function downloadImage(Response $imageResponse): string
    {
        $image = "";
        try {
            while (!$imageResponse->getBody()->eof()) {
                $image .= $imageResponse->getBody()->read(8192);

                if ($imageResponse->getBody()->tell() >= UnfurlResponse::MAX_IMAGE_SIZE) {
                    throw new DownloadFailed("Image is too large.");
                }
            }
        } finally {
            $imageResponse->getBody()->close();
        }

        return $image;
    }

    private function validateImage(array $imageData): bool
    {
        $isSquared = $imageData[0] === $imageData[1];
        if (
            (!$isSquared && ($imageData[0] < 300 && $imageData[1] < 150))
            || \min($imageData[0], $imageData[1]) < 50
        ) {
            return false;
        }

        if (!$this->getImageExtension($imageData)) {
            return false;
        }

        return true;
    }

    private function saveImage(array $imageData, string $image): string
    {
        $imageHash = \sha1($image);

        $path = WCF_DIR . UnfurlUrl::IMAGE_DIR . \substr($imageHash, 0, 2);
        FileUtil::makePath($path);

        $extension = $this->getImageExtension($imageData);

        $fileLocation = $path . '/' . $imageHash . '.' . $extension;

        \file_put_contents($fileLocation, $image);

        return $imageHash;
    }

    private function getImageExtension(array $imageData): ?string
    {
        switch ($imageData[2]) {
            case \IMAGETYPE_PNG:
                return 'png';

            case \IMAGETYPE_GIF:
                return 'gif';

            case \IMAGETYPE_JPEG:
                return 'jpg';

            default:
                return null;
        }
    }

    private function save(
        string $status,
        string $title = "",
        string $description = "",
        ?int $imageID = null,
        array $imageData = []
    ): void {
        switch ($status) {
            case UnfurlUrl::STATUS_PENDING:
            case UnfurlUrl::STATUS_REJECTED:
            case UnfurlUrl::STATUS_SUCCESSFUL:
                break;

            default:
                throw new BadMethodCallException("Invalid status '{$status}' given.");
        }

        if ($imageID !== null && !empty($imageData)) {
            throw new BadMethodCallException("You cannot pass an imageID and imageData at the same time.");
        }

        $urlAction = new UnfurlUrlAction([$this->urlID], 'update', [
            'data' => [
                'status' => $status,
                'title' => $title,
                'description' => $description,
                'imageID' => $imageID,
                'lastFetch' => TIME_NOW,
            ],
            'imageData' => $imageData,
        ]);
        $urlAction->executeAction();
    }

    /**
     * @inheritDoc
     */
    public function onFinalFailure()
    {
        $this->save(UnfurlUrl::STATUS_REJECTED);
    }
}
