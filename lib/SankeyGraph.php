<?php

/**
 * 2D point
 **/
class skPoint
{
  public
    $x = 0,
    $y = 0;

  /**
   * @constructor
   *
   * @param int $x
   * @param int $y
   **/
  public function __construct($x, $y)
  {
    $this->x = (int)$x;
    $this->y = (int)$y;
  }

  /**
   * @return a new skPoint object with X coordinate shifted by $displacement
   */
  public function shiftX($displacement)
  {
    $obj = clone $this;
    $obj->x += (int)$displacement;
    return $obj;
  }

  /**
   * @return a new skPoint object with Y coordinate shifted by $displacement
   */
  public function shiftY($displacement)
  {
    $obj = clone $this;
    $obj->y += (int)$displacement;
    return $obj;
  }

  /**
   * @return a new skPoint object with coordinates translated by amount of $displacementX and $displacementY
   */
  public function translate($displacementX, $displacementY)
  {
    $obj = clone $this;
    $obj->x += (int)$displacementX;
    $obj->y += (int)$displacementY;
    return $obj;
  }

  /**
   * @return skPoint in string format. Ex. (4, 5)
   **/
  public function __toString()
  {
    return self::toString($this);
  }

  /**
   * Static method to get points in array format
   *
   * @param skPoint $obj
   * @return skPoint in array format
   **/
  static public function toArray(skPoint $obj)
  {
    return array('x' => $obj->x, 'y' => $obj->y);
  }

  /**
   * Static method to get points in string format
   *
   * @param skPoint $obj
   * @return skPoint in string format
   **/
  static public function toString(skPoint $obj)
  {
    return "({$obj->x}, {$obj->y})";
  }
}

/**
 * Holds a vector of 2D points
 **/
class skPolygon
{
  private
    $poly = array();

  /**
   * @constructor
   **/
  public function __construct()
  {
    $this->reset();
  }

  /**
   * Queues a new point
   *
   * @param skPoint $p
   * @return skPolygon
   **/
  public function add(skPoint $p)
  {
    $this->poly[] = $p;
    return $this;
  }

  /**
   * Clears polygon points
   **/
  public function reset()
  {
    $this->poly = array();
  }

  /**
   * @returns array of Points
   */
  public function get()
  {
    return $this->poly;
  }

  /**
   * @return String representation of skPolygon
   **/
  public function __toString()
  {
    $mapped_values = array_map(array('skPoint','toString'), $this->poly);
    return '[ '.implode('; ', $mapped_values).' ]';
  }

  /**
   * @return array representation of skPolygon
   **/
  public function toArray()
  {
    return array_map(array('skPoint','toArray'), $this->poly);
  }
}

/**
 * Canvas margins
 **/
class skCanvas
{
  public
    $top    = 0.0,
    $left   = 0.0,
    $bottom = 0.0,
    $right  = 0.0;

  public function __construct($top, $left, $bottom, $right)
  {
    $this->top    = (float)$top;
    $this->left   = (float)$left;
    $this->bottom = (float)$bottom;
    $this->right  = (float)$right;
  }
}


class SankeyGraph
{
  private
    $width         = 750,
    $height        = 300,
    $arrow_pad     = 15,
    $margin_factor = 0.02,
    $color,
    $m,             // Canvas margins
    $iCoords,       // input coordinates
    $oCoords,       // losses coordinates
    $inputs,
    $losses,
    $iLabels,
    $oLabels,
    $imgBuff;

  const DEBUG = false;


  /**
   *
   * @param array $inputs
   * @param array $losses
   */
  public function __construct(array $inputs, array $losses)
  {
    $this->imgBuff = new Imagick();
    $this->imgBuff->newImage($this->width, $this->height, new ImagickPixel('white'));
    $this->color = new ImagickPixel('black');

    $this->iCoords = array();
    $this->oCoords = array();

    $this->inputs = array_keys($inputs);
    $this->iLabels = array_values($inputs);
    asort($this->inputs);

    $this->losses = array_keys($losses);
    $this->oLabels = array_values($losses);
    asort($this->losses);
  }

  /**
   *
   * @param string $file Output file
   * @return void
   */
  public function drawTo($file)
  {
    if (empty($this->inputs) || empty($this->losses)) {
      return false;
    }

    // set canvas
    $this->m = new skCanvas($this->height * $this->margin_factor*3, // top
                            $this->width * $this->margin_factor,  // left
                            $this->height/1.1 - ($this->height * $this->margin_factor), // bottom
                            $this->width - ($this->width * $this->margin_factor));      // right

    $this->drawInputsPoints();
    $this->drawFirstLoss();
    $this->drawLossesArcs();

    $this->imgBuff->setImageFormat("png");
    file_put_contents($file, $this->imgBuff->getImageBlob());

    // cleanup
    $this->imgBuff->destroy();
  }

  /**
   *
   *
   */
  private function drawInputsPoints()
  {
    /*
      2D point alias:

      tl -> top-left
      tr -> top-right
      bl -> bottom-left
      br -> bottom-right
     */
    $totalInputs = array_sum($this->inputs);

    $draw = new ImagickDraw();
    $draw->setStrokeWidth(1);

    for ($n = 0; $n < count($this->inputs); $n++) {
      $percentage = current($this->inputs)/$totalInputs;
      $inputHeight = $percentage*($this->m->bottom - $this->m->top);
      $padding = (int)($n*$this->width*0.03); // indent 3% each iteration

      // top right coords
      if ($n == 0) {
        $this->iCoords['tr'.$n] = new skPoint($this->width*0.3, $this->m->top);
      } else {
        $this->iCoords['tr'.$n] = clone $this->iCoords['br'.($n-1)];
      }

      // top left coords
      if ($n == 0) {
        $this->iCoords['tl'.$n] = new skPoint($this->m->left, $this->m->top);
      } else {
        $this->iCoords['tl'.$n] = $this->iCoords['bl'.($n-1)];
        $this->iCoords['tl'.$n]->x += $padding;
      }

      // bottom left coords
      if ($n == 0) {
        $this->iCoords['bl'.$n] = new skPoint($this->m->left, $inputHeight);
      } else {
        $this->iCoords['bl'.$n] = $this->iCoords['bl'.($n-1)]->shiftY($inputHeight);
      }

      // bottom right coords
      if ($n == 0) {
        $this->iCoords['br'.$n] = new skPoint($this->width*0.3, $inputHeight);
      } else {
        $this->iCoords['br'.$n] = new skPoint($this->width*0.3,
                                            ($inputHeight + $this->iCoords['bl'.($n-1)]->y));
      }

      // draw from right to left upper line
      $poly = new skPolygon();
      $poly->add($this->iCoords['tr'.$n])
           ->add($this->iCoords['tl'.$n]);

      // arrow medium point
      $leftX = $this->m->left;
      $medium_point = new skPoint($leftX,
                                ($this->iCoords['bl'.$n]->y - $this->iCoords['tl'.$n]->y)/2);

      if ($n > 0) { // padded arrow
        $medium_point->y += $this->iCoords['bl'.($n-1)]->y;
        $leftX = $this->iCoords['bl'.($n-1)]->x;
        $medium_point->x = $leftX;
      }
      else {
        $medium_point->y += $this->m->top;
      }

      // draw arrow and bottom line
      $poly->add(new skPoint($leftX, $this->iCoords['tl'.$n]->y))
           ->add($medium_point->shiftX($this->arrow_pad))
           ->add(new skPoint($leftX, $this->iCoords['bl'.$n]->y))
           ->add($this->iCoords['bl'.$n])
           ->add($this->iCoords['br'.$n]);

      // draw label
      $label = $this->iLabels[key($this->inputs)].' ('.number_format($percentage*100, 2).'%)';
      $text_metrics = $this->imgBuff->queryFontMetrics($draw, $label);

      $draw->annotation(
        $medium_point->x + (int)$this->arrow_pad*2,
        $medium_point->y + (int)$text_metrics['ascender']/2,
        $label);

      if (self::DEBUG) {
        echo "input $n = ". (string)$poly . "\n";
      }

      //$draw->setStrokeColor(new ImagickPixel($n%2?'red':'black'));
      $draw->setStrokeColor($this->color);
      $draw->setFillColor('transparent');
      $draw->polyline($poly->toArray());
      $this->imgBuff->drawImage($draw);

      $draw->clear();
      unset($poly);
      next($this->inputs);
    }

    $draw->destroy();
    reset($this->inputs);
  }


  private function drawFirstLoss()
  {
    $totalLosses = array_sum($this->losses);

    $draw = new ImagickDraw();
    $draw->setStrokeWidth(1);

    $last = (count($this->iCoords)/4) - 1;
    $percentage = current($this->losses)/$totalLosses;
    $lossHeight = $percentage*($this->iCoords['br'.$last]->y - $this->iCoords['tr0']->y);

    $mediumPoint = null;
    $this->oCoords['tl0'] = clone $this->iCoords['tr0'];
    $this->oCoords['tr0'] = new skPoint($this->m->right - 15, $this->m->top);

    $this->oCoords['bl0'] = $this->iCoords['tr0']->shiftY($lossHeight);
    $this->oCoords['br0'] = $this->oCoords['tr0']->shiftY($lossHeight);

    $mediumPoint = new skPoint($this->oCoords['tr0']->x + $this->arrow_pad,
                             ($this->oCoords['bl0']->y - $this->iCoords['tl0']->y)/2);

    $poly = new skPolygon();
    $poly->add($this->oCoords['tl0'])
         ->add($this->oCoords['tr0'])
         ->add($this->oCoords['tr0']->shiftY(-$this->arrow_pad/3))
         ->add($mediumPoint->shiftY($this->oCoords['tr0']->y))
         ->add($this->oCoords['br0']->shiftY($this->arrow_pad/3))
         ->add($this->oCoords['br0'])
         ->add($this->oCoords['bl0']);

    // draw label
    $label = $this->oLabels[key($this->losses)].' ('.number_format($percentage*100, 2).'%)';
    $text_metrics = $this->imgBuff->queryFontMetrics($draw, $label);

    $draw->annotation(
      $this->oCoords['tr0']->shiftX(-$text_metrics['textWidth'])->x,
      $this->oCoords['tr0']->shiftY(-$this->arrow_pad + $text_metrics['ascender']/2)->y,
      $label);

    if (self::DEBUG) {
      echo "first loss = " . (string)$poly . "\n";
    }

    $draw->setStrokeColor($this->color);
    $draw->setFillColor('transparent');
    $draw->polyline($poly->toArray());
    $this->imgBuff->drawImage($draw);

    $draw->clear();
    unset($poly);
    $draw->destroy();
    reset($this->losses);
  }


  /**
   *
   */
  private function drawLossesArcs()
  {
    $totalLosses = array_sum($this->losses);
    $draw = new ImagickDraw();

    $_losses = $this->losses;
    unset($_losses[key($_losses)]); // skip first loss
    arsort($_losses); // bigger losses first

    for ($n = 1; $n <= count($_losses); $n++) {
      $last = (count($this->iCoords)/4) - 1;
      $percentage = current($_losses)/$totalLosses;
      $lossHeight = $percentage*($this->iCoords['br'.$last]->y - $this->iCoords['tr0']->y);
      $padding = (int)($n*0.16*($this->m->right - $this->m->left)); // outdent width 16% each iteration


      $this->oCoords['tl'.$n] = $this->oCoords['bl'.($n-1)];
      $this->oCoords['tr'.$n] = new skPoint($this->oCoords['br0']->x - $padding,
                                          $this->oCoords['br'.($n-1)]->y);

      $this->oCoords['bl'.$n] = $this->oCoords['tl'.$n]->shiftY($lossHeight);
      $this->oCoords['br'.$n] = $this->oCoords['tr'.$n]->shiftY($lossHeight);

      // plot from left to right upper line
      $poly = new skPolygon();
      $poly->add($this->oCoords['tl'.$n])
           ->add($this->oCoords['tr'.$n]);
      $draw->polyline($poly->toArray());

      if (self::DEBUG) {
        echo "loss $n = ". (string)$poly . "\n";
      }

      $draw->setFillColor(new ImagickPixel('transparent'));
      $draw->setStrokeColor($this->color);
      // plot major arc
      $draw->arc(
        $this->oCoords['tr'.$n]->x - (int)$lossHeight, // X start point
        $this->oCoords['tr'.$n]->y,                    // Y start point
        $this->oCoords['tr'.$n]->x + (int)$lossHeight,
        $this->oCoords['tr'.$n]->y + (int)$lossHeight*3,
        270,
        0);

      // plot minor arc
      $draw->arc(
        $this->oCoords['br'.$n]->x - (int)$lossHeight/2,
        $this->oCoords['br'.$n]->y,
        $this->oCoords['br'.$n]->x + (int)$lossHeight/2,
        $this->oCoords['br'.$n]->y + (int)$lossHeight,
        270,
        0);

      // plot arrows
      $poly->reset();
      $major_arc_edge = $this->oCoords['tr'.$n]->translate($lossHeight, $lossHeight*1.5);
      $minor_arc_edge = $this->oCoords['br'.$n]->translate($lossHeight/2, $lossHeight/2);
      $medium_point = new skPoint($minor_arc_edge->x + ($major_arc_edge->x - $minor_arc_edge->x)/2,
                                $major_arc_edge->y + $this->arrow_pad);

      $poly->add($major_arc_edge)
           ->add($major_arc_edge->shiftX($this->arrow_pad/3))
           ->add($medium_point)
           ->add($minor_arc_edge->shiftX(-$this->arrow_pad/3))
           ->add($minor_arc_edge);
      $draw->polyline($poly->toArray());

      // plot from right to left bottom line
      $poly->reset();
      $poly->add($this->oCoords['br'.$n])
           ->add($this->oCoords['bl'.$n]);

      $draw->polyline($poly->toArray());
      $this->imgBuff->drawImage($draw);
      $draw->clear();

      // draw label
      $label = $this->oLabels[key($_losses)].' ('.number_format($percentage*100, 2).'%)';
      $text_metrics = $this->imgBuff->queryFontMetrics($draw, $label);

      $draw->annotation(
        $medium_point->shiftX(-$text_metrics['textWidth']/2)->x,
        $medium_point->shiftY($this->arrow_pad)->y,
        $label);

      if (self::DEBUG) {
        echo "loss $n = ". (string)$poly . "\n";
      }

      $this->imgBuff->drawImage($draw);

      $draw->clear();
      unset($poly);
      next($_losses);
    }

    $draw->destroy();
  }
}

