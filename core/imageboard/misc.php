<?php declare(strict_types=1);
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Misc functions                                                            *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/**
 * Add a directory full of images
 *
 * @param string $base
 * @return array
 */
function add_dir(string $base): array
{
    $results = [];

    foreach (list_files($base) as $full_path) {
        $short_path = str_replace($base, "", $full_path);
        $filename = basename($full_path);

        $tags = path_to_tags($short_path);
        $result = "$short_path (".str_replace(" ", ", ", $tags).")... ";
        try {
            add_image($full_path, $filename, $tags);
            $result .= "ok";
        } catch (UploadException $ex) {
            $result .= "failed: ".$ex->getMessage();
        }
        $results[] = $result;
    }

    return $results;
}

/**
 * Sends a DataUploadEvent for a file.
 *
 * @param string $tmpname
 * @param string $filename
 * @param string $tags
 * @throws UploadException
 */
function add_image(string $tmpname, string $filename, string $tags): void
{
    assert(file_exists($tmpname));

    $pathinfo = pathinfo($filename);
    $metadata = [];
    $metadata['filename'] = $pathinfo['basename'];
    if (array_key_exists('extension', $pathinfo)) {
        $metadata['extension'] = $pathinfo['extension'];
    }

    $metadata['tags'] = Tag::explode($tags);
    $metadata['source'] = null;
    send_event(new DataUploadEvent($tmpname, $metadata));
}

/**
 * Given a full size pair of dimensions, return a pair scaled down to fit
 * into the configured thumbnail square, with ratio intact.
 * Optionally uses the High-DPI scaling setting to adjust the final resolution.
 *
 * @param int $orig_width
 * @param int $orig_height
 * @param bool $use_dpi_scaling Enables the High-DPI scaling.
 * @return array
 */
function get_thumbnail_size(int $orig_width, int $orig_height, bool $use_dpi_scaling = false): array
{
    global $config;

    if ($orig_width === 0) {
        $orig_width = 192;
    }
    if ($orig_height === 0) {
        $orig_height = 192;
    }

    if ($orig_width > $orig_height * 5) {
        $orig_width = $orig_height * 5;
    }
    if ($orig_height > $orig_width * 5) {
        $orig_height = $orig_width * 5;
    }


    if ($use_dpi_scaling) {
        list($max_width, $max_height) = get_thumbnail_max_size_scaled();
    } else {
        $max_width = $config->get_int(ImageConfig::THUMB_WIDTH);
        $max_height = $config->get_int(ImageConfig::THUMB_HEIGHT);
    }

    $output = get_scaled_by_aspect_ratio($orig_width, $orig_height, $max_width, $max_height);

    if ($output[2] > 1 && $config->get_bool('thumb_upscale')) {
        return [(int)$orig_width, (int)$orig_height];
    } else {
        return $output;
    }
}

function get_scaled_by_aspect_ratio(int $original_width, int $original_height, int $max_width, int $max_height) : array
{
    $xscale = ($max_width/ $original_width);
    $yscale = ($max_height/ $original_height);

    $scale = ($yscale < $xscale) ? $yscale : $xscale ;

    return [(int)($original_width*$scale), (int)($original_height*$scale), $scale];
}

/**
 * Fetches the thumbnails height and width settings and applies the High-DPI scaling setting before returning the dimensions.
 *
 * @return array [width, height]
 */
function get_thumbnail_max_size_scaled(): array
{
    global $config;

    $scaling = $config->get_int(ImageConfig::THUMB_SCALING);
    $max_width  = $config->get_int(ImageConfig::THUMB_WIDTH) * ($scaling/100);
    $max_height = $config->get_int(ImageConfig::THUMB_HEIGHT) * ($scaling/100);
    return [$max_width, $max_height];
}


function create_image_thumb(string $hash, string $type, string $engine = null)
{
    $inname = warehouse_path(Image::IMAGE_DIR, $hash);
    $outname = warehouse_path(Image::THUMBNAIL_DIR, $hash);
    $tsize = get_thumbnail_max_size_scaled();
    create_scaled_image($inname, $outname, $tsize, $type, $engine);
}

function create_scaled_image(string $inname, string $outname, array $tsize, string $type, ?string $engine)
{
    global $config;
    if (empty($engine)) {
        $engine = $config->get_string(ImageConfig::THUMB_ENGINE);
    }

    $output_format = $config->get_string(ImageConfig::THUMB_TYPE);
    if ($output_format=="webp") {
        $output_format = Media::WEBP_LOSSY;
    }

    send_event(new MediaResizeEvent(
        $engine,
        $inname,
        $type,
        $outname,
        $tsize[0],
        $tsize[1],
        false,
        $output_format,
        $config->get_int(ImageConfig::THUMB_QUALITY),
        true,
        $config->get_bool('thumb_upscale', false)
    ));
}
