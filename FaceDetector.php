<?php
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
//
// @Author Karthik Tharavaad
//         karthik_tharavaad@yahoo.com
// @Contributor Maurice Svay
//              maurice@svay.Com
// @Contributor Laurent Goussard
//              loranger@free.fr

class FaceDetector
{
    protected $detection_data;
    protected $im;
    protected $small;
    protected $face;
    protected $padding;

    public function __construct($file)
    {
        $this->loadDetectionData('detection.dat');
        $this->im = new Imagick($file);
        $this->small = clone $this->im;

        $this->small->thumbnailImage(320, 240, true);

        $stats = $this->getImgStats($this->small);
        $this->face = $this->detectGreedyBigToSmall($stats['ii'], $stats['ii2'], $stats['width'], $stats['height']);
        $this->face['x'] *= $this->im->getImageWidth() / $this->small->getImageWidth();
        $this->face['y'] *= $this->im->getImageHeight() / $this->small->getImageHeight();
        $this->face['w'] *= $this->im->getImageWidth() / $this->small->getImageWidth();
        $this->setPadding( $this->face['w'] / 3 );
    }

    public function loadDetectionData($source)
    {
        if (is_file($source)) {
            $this->detection_data = unserialize(file_get_contents($source));
        } else {
            throw new Exception("Couldn't load detection data");
        }
    }

    public function setPadding($padding)
    {
        $this->padding = (int) $padding;
    }

    public function crop()
    {
        $this->im->cropImage($this->face['w'] + $this->padding * 2, $this->face['w'] + $this->padding * 2, $this->face['x'] - $this->padding, $this->face['y'] - $this->padding);
    }

    public function highlight()
    {
        $draw = new ImagickDraw();

        $draw->setFillColor('transparent');
        $draw->setStrokeColor( new ImagickPixel( 'white' ) );
        $draw->setStrokeWidth( 2 );

        $x = $this->face['x'] - $this->padding;
        $y = $this->face['y'] - $this->padding;
        $radius = min( round($this->face['w']/5), 20);
        $draw->roundRectangle( $x, $y, $x + $this->face['w'] + $this->padding * 2, $y + $this->face['w'] + $this->padding * 2, $radius, $radius );

        $rect = new Imagick();
        $rect->newImage( $this->im->getImageWidth(), $this->im->getImageHeight(), new ImagickPixel('transparent'), "png" );
        $rect->drawImage($draw);
        $this->im->compositeImage( $rect, imagick::COMPOSITE_DIFFERENCE, 0, 0 );
    }

    public function display($type = 'jpg')
    {
        $this->im->setImageFormat( $type );
        header( "Content-Type: image/" . $this->im->getImageFormat() );
        echo $this->im;
    }

    public function getImage()
    {
        return $this->im;
    }

    public function save($destination = false)
    {
        $this->im->writeImage($destination);
    }

    public function toJson()
    {
        return json_encode( array( 'x' => $this->face['x'], 'y' => $this->face['y'], 'w' => $this->face['w'] ) );
    }

    public function getFace()
    {
        return $this->face;
    }

    protected function getImgStats($imagick)
    {
        $iis =  $this->computeIi($imagick);

        return array(
            'width' => $imagick->getImageWidth(),
            'height' => $imagick->getImageHeight(),
            'ii' => $iis['ii'],
            'ii2' => $iis['ii2']
        );
    }

    protected function computeIi($imagick )
    {
        $ii_w = $imagick->getImageWidth()+1;
        $ii_h = $imagick->getImageHeight()+1;
        $ii = array();
        $ii2 = array();

        for ($i=0; $i<$ii_w; $i++ ) {
            $ii[$i] = 0;
            $ii2[$i] = 0;
        }

        for ($i=1; $i<$ii_w; $i++ ) {
            $ii[$i*$ii_w] = 0;
            $ii2[$i*$ii_w] = 0;
            $rowsum = 0;
            $rowsum2 = 0;
            for ($j=1; $j<$ii_h; $j++ ) {
                $pixel = $imagick->getImagePixelColor($j, $i);

                $red = $pixel->getColorValue(imagick::COLOR_RED) * 255;
                $green = $pixel->getColorValue(imagick::COLOR_GREEN) * 255;
                $blue = $pixel->getColorValue(imagick::COLOR_BLUE) * 255;

                $grey = ( 0.2989*$red + 0.587*$green + 0.114*$blue )>>0;  // this is what matlab uses
                $rowsum += $grey;
                $rowsum2 += $grey*$grey;

                $ii_above = ($i-1)*$ii_w + $j;
                $ii_this = $i*$ii_w + $j;

                $ii[$ii_this] = $ii[$ii_above] + $rowsum;
                $ii2[$ii_this] = $ii2[$ii_above] + $rowsum2;
            }
        }

        return array('ii'=>$ii, 'ii2' => $ii2);
    }

    protected function detectGreedyBigToSmall( $ii, $ii2, $width, $height )
    {
        $s_w = $width/20.0;
        $s_h = $height/20.0;
        $start_scale = $s_h < $s_w ? $s_h : $s_w;
        $scale_update = 1 / 1.2;
        for ($scale = $start_scale; $scale > 1; $scale *= $scale_update ) {
            $w = (20*$scale) >> 0;
            $endx = $width - $w - 1;
            $endy = $height - $w - 1;
            $step = max( $scale, 2 ) >> 0;
            $inv_area = 1 / ($w*$w);
            for ($y = 0; $y < $endy ; $y += $step ) {
                for ($x = 0; $x < $endx ; $x += $step ) {
                    $passed = $this->detectOnSubImage( $x, $y, $scale, $ii, $ii2, $w, $width+1, $inv_area);
                    if ( $passed ) {
                        return array('x'=>$x, 'y'=>$y, 'w'=>$w);
                    }
                } // end x
            } // end y
        }  // end scale

        return array('x'=>0, 'y'=>0, 'w'=>0);
    }

    protected function detectOnSubImage( $x, $y, $scale, $ii, $ii2, $w, $iiw, $inv_area)
    {

        $mean = @( $ii[($y+$w)*$iiw + $x + $w] + $ii[$y*$iiw+$x] - $ii[($y+$w)*$iiw+$x] - $ii[$y*$iiw+$x+$w]  )*$inv_area;
        $vnorm =  @( $ii2[($y+$w)*$iiw + $x + $w] + $ii2[$y*$iiw+$x] - $ii2[($y+$w)*$iiw+$x] - $ii2[$y*$iiw+$x+$w]  )*$inv_area - ($mean*$mean);
        $vnorm = $vnorm > 1 ? sqrt($vnorm) : 1;

        $passed = true;
        for ($i_stage = 0; $i_stage < count($this->detection_data); $i_stage++ ) {
            $stage = $this->detection_data[$i_stage];
            $trees = $stage[0];

            $stage_thresh = $stage[1];
            $stage_sum = 0;

            for ($i_tree = 0; $i_tree < count($trees); $i_tree++ ) {
                $tree = $trees[$i_tree];
                $current_node = $tree[0];
                $tree_sum = 0;
                while ( $current_node != null ) {
                    $vals = $current_node[0];
                    $node_thresh = $vals[0];
                    $leftval = $vals[1];
                    $rightval = $vals[2];
                    $leftidx = $vals[3];
                    $rightidx = $vals[4];
                    $rects = $current_node[1];

                    $rect_sum = 0;
                    for ( $i_rect = 0; $i_rect < count($rects); $i_rect++ ) {
                        $s = $scale;
                        $rect = $rects[$i_rect];
                        $rx = ($rect[0]*$s+$x)>>0;
                        $ry = ($rect[1]*$s+$y)>>0;
                        $rw = ($rect[2]*$s)>>0;
                        $rh = ($rect[3]*$s)>>0;
                        $wt = $rect[4];

                        $r_sum = @( $ii[($ry+$rh)*$iiw + $rx + $rw] + $ii[$ry*$iiw+$rx] - $ii[($ry+$rh)*$iiw+$rx] - $ii[$ry*$iiw+$rx+$rw] )*$wt;
                        $rect_sum += $r_sum;
                    }

                    $rect_sum *= $inv_area;

                    $current_node = null;
                    if ( $rect_sum >= $node_thresh*$vnorm ) {
                        if( $rightidx == -1 )
                            $tree_sum = $rightval;
                        else
                            $current_node = $tree[$rightidx];
                    } else {
                        if( $leftidx == -1 )
                            $tree_sum = $leftval;
                        else
                            $current_node = $tree[$leftidx];
                    }
                }
                $stage_sum += $tree_sum;
            }
            if ( $stage_sum < $stage_thresh ) {
                return false;
            }
        }

        return true;
    }
}
