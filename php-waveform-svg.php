<?php

  ini_set("max_execution_time", "30000");

  // how much detail we want. Larger number means less detail
  // (basically, how many bytes/frames to skip processing)
  // the lower the number means longer processing time
  define("DETAIL", 50);

  define("VIEWPORT_WIDTH", 700);
  define("VIEWPORT_HEIGHT", 100);
  define("VIEWBOX_WIDTH", 100);
  define("VIEWBOX_HEIGHT", 100);

  define("DEFAULT_FOREGROUND", "#FF0000");
  define("DEFAULT_BACKGROUND", "#FFFFFF");

  /**
   * GENERAL FUNCTIONS
   */
  function findValues($byte1, $byte2){
    $byte1 = hexdec(bin2hex($byte1));
    $byte2 = hexdec(bin2hex($byte2));
    return ($byte1 + ($byte2*256));
  }

  function flipYValue($data) {
    return VIEWBOX_HEIGHT - $data;
  }

  if (isset($_FILES["wav-left"]) && isset($_FILES["wav-right"])) {

    /**
     * PROCESS THE FILE
     */

		// array of wavs that need to be processed
    $wavs_to_process = array(
      $_FILES["wav-left"]["tmp_name"],
      $_FILES["wav-right"]["tmp_name"]
    );

    // Could just print to the output buffer, but saving to a variable
    // makes it easier to display the SVG and dump it to a file without
    // any messy ob_*() hassle
    $svg  = "<?xml version=\"1.0\"?>\n";
    $svg .= "<?xml-stylesheet href=\"waveform.css\" type=\"text/css\"?>\n";
    $svg .= "<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n";
    $svg .= "<svg width=\"". VIEWPORT_WIDTH . "\" height=\"". VIEWPORT_HEIGHT . "\" viewBox=\"0,0,100,100\" preserveAspectRatio=\"none\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">\n";

    $y_offset = floor(1 / sizeof($wavs_to_process) * VIEWBOX_HEIGHT);

    $baseline = VIEWBOX_HEIGHT / 2;

    // process each wav individually
    for($wav = 1; $wav <= sizeof($wavs_to_process); $wav++) {

      $svg .= "<polyline class=\"polyline-{$wav}\" points=\"";

      $filename = $wavs_to_process[$wav - 1];

      /**
       * Below as posted by "zvoneM" on
       * http://forums.devshed.com/php-development-5/reading-16-bit-wav-file-318740.html
       * as findValues() defined above
       * Translated from Croation to English - July 11, 2011
       */
      $handle = fopen($filename, "r");
      // wav file header retrieval
      $heading[] = fread($handle, 4);
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = fread($handle, 4);
      $heading[] = fread($handle, 4);
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = fread($handle, 4);
      $heading[] = bin2hex(fread($handle, 4));

      // wav bitrate
      $peek = hexdec(substr($heading[10], 0, 2));
      $byte = $peek / 8;

      // checking whether a mono or stereo wav
      $channel = hexdec(substr($heading[6], 0, 2));

      $ratio = ($channel == 2 ? 40 : 80);

      // start putting together the initial canvas
      // $data_size = (size_of_file - header_bytes_read) / skipped_bytes + 1
      $data_size = floor((filesize($filename) - 44) / ($ratio + $byte) + 1);
      $data_point = 0;

      while(!feof($handle) && $data_point < $data_size){
        if ($data_point++ % DETAIL == 0) {
          $bytes = array();

          // get number of bytes depending on bitrate
          for ($i = 0; $i < $byte; $i++)
            $bytes[$i] = fgetc($handle);

          switch($byte){
            // get value for 8-bit wav
            case 1:
              $data = findValues($bytes[0], $bytes[1]);
              break;
            // get value for 16-bit wav
            case 2:
              if(ord($bytes[1]) & 128)
                $temp = 0;
              else
                $temp = 128;
              $temp = chr((ord($bytes[1]) & 127) + $temp);
              $data = floor(findValues($bytes[0], $temp) / 256);
              break;
          }

          // Squash data between 0 and 100
          $data = number_format($data / 255 * 100, 2);

          // skip bytes for memory optimization
          fseek($handle, $ratio, SEEK_CUR);

          $x = number_format($data_point / $data_size * 100, 2);

          $amplitude = abs($data - $baseline);

          // TODO: figure out a better way to scale
          $amplitude *= 4;

          $y = $wav === 1 ? $baseline - $amplitude : $baseline + $amplitude;

          $svg .= "$x,$y ";

        } else {
          // skip this one due to lack of detail
          fseek($handle, $ratio + $byte, SEEK_CUR);
        }
      }

      $svg = rtrim($svg, ' ') . "\" />";

      // close and cleanup
      fclose($handle);

      // delete the processed wav file
      unlink($filename);

    }

    $svg .= "\n</svg>";

    header("Content-Type: image/svg+xml");

    print $svg;

  } else {

?>

  <form method="post" action="<?php print $_SERVER["REQUEST_URI"]; ?>" enctype="multipart/form-data">

  <p>WAV File (left):<br />
    <input type="file" name="wav-left" /></p>

  <p>WAV File (right):<br />
    <input type="file" name="wav-right" /></p>
  <p><input type="submit" value="Generate Waveform" /></p>

  </form>

<?php

  }
