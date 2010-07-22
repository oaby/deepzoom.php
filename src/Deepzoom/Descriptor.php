<?php
namespace Deepzoom;

/**
* Deep Zoom Tools
*
* Copyright (c) 2008-2010, OpenZoom <http://openzoom.org/>
* Copyright (c) 2008-2010, Nicolas Fabre <nicolas.fabre@gmail.com>
* All rights reserved.
*
* Redistribution and use in source and binary forms, with or without modification,
* are permitted provided that the following conditions are met:
*
* 1. Redistributions of source code must retain the above copyright notice,
* this list of conditions and the following disclaimer.
*
* 2. Redistributions in binary form must reproduce the above copyright
* notice, this list of conditions and the following disclaimer in the
* documentation and/or other materials provided with the distribution.
*
* 3. Neither the name of OpenZoom nor the names of its contributors may be used
* to endorse or promote products derived from this software without
* specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
* ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
* WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
* DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
* ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
* (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
* LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
* ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
* (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
* SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/**
 * Descriptor 
 *
 * @package    Deepzoom
 * @subpackage Descriptor
 * @author     Nicolas Fabre <nicolas.fabre@gmail.com>
 */
class Descriptor implements DescriptorInterface {
	/**
	 * Width of the original image 
	 *
	 * @var int
	 */
	protected $_width;
	
	/**
	 * Height of the original image 
	 *
	 * @var int
	 */
	protected $_height;
	
	/**
	 * Tile size
	 *
	 * @var int
	 */
	protected $_tileSize;
	
	/**
	 * Tile overlap
	 *
	 * @var float
	 */
	protected $_tileOverlap;
	
	/**
	 * Tile format
	 *
	 * @var string
	 */
	protected $_tileFormat;
	
	/**
	 * Number of levels in the pyramid
	 *
	 * @var int
	 */
	protected $_numLevels = null;	
	
	/**
	 * Store Dimensions to avoid the recalculation
	 * @var array
	 */
	protected $_dimensions = array();	
	/**
	 * Constructor
	 *
	 * @param int $width
	 * @param int $height
	 * @param int $tileSize
	 * @param int $tileOverlap
	 * @param string $tileFormat
	 * @return void
	 */
	public function __construct($width=null,$height=null,$tileSize=254,$tileOverlap=1,$tileFormat="jpg") {
		$this->_width = $width;
        $this->_height = $height;
        $this->_tileSize = $tileSize;
        $this->_tileOverlap = $tileOverlap;
        $this->_tileFormat = $tileFormat;
	}
		
	/**
	 * Intialize descriptor from an existing descriptor file
     *
	 * @param string $source
	 * 
	 * @return Deepzoom\Descriptor Fluent interface
	 * @throw Deepzoom\Exception check pyramid level
	 */
	public function open($source) {
		if (file_exists($source)) {
			$xml = simplexml_load_file($source);
			$this->_width = (int)$xml->Size["Width"];
	        $this->_height = (int)$xml->Size["Height"];
	        $this->_tileSize = (int)$xml["TileSize"];
	        $this->_tileOverlap = (int)$xml["Overlap"];
	        $this->_tileFormat = (string)$xml["Format"];
		} else throw new Exception('File not found : '.$source);
		
	    return $this;
	}
	
	/**
     * Save descriptor file
     *
     * @param string $source
     * 
     * @return Deepzoom\Descriptor Fluent interface
     */ 
	public function save($destination) {
		file_put_contents($destination,$this->dump());
		
		return $this;	
	}
	
    /**
     * Dumps the descriptor container as an XML string.
     *
     * @return string An xml string representing of the descriptor container
     */
    public function dump()
    {
        return $this->startXml().$this->addTileParameters().$this->addImageParameters().$this->endXml();
    }
    
    
 	/**
	 * Number of levels in the pyramid
	 *
	 * @return int
	 */
	public function getNumLevels() {
        if (empty($this->_numLevels)) {
            $maxDimension = max(array($this->_width,$this->_height));
            $this->_numLevels = (int)ceil(log($maxDimension,2)) + 1;
        }
        
        return $this->_numLevels;
	}
	
	/**
	 * Scale of a pyramid level
	 *
	 * @param int $level
	 * @return float
	 * @throw Deepzoom\Exception check pyramid level
	 */
	public function getScale($level) {
		if(0 <= $level && $level < $this->getNumLevels()) {
			$maxLevel = $this->getNumLevels() - 1 ;
			
			return pow(0.5,$maxLevel - $level);
		} else throw new Exception("Invalid pyramid level (scale)");
	} 
	
	/**
	 * Dimensions of level (width, height)
	 *
	 * @param int $level
	 * @return array
	 * @throw Deepzoom\Exception check pyramid level
	 */
	public function getDimension($level) {
		$key = $this->getDimensionKey($level);
		if(!$this->getCachedDimension($key)) {
			if(0 <= $level and $level < $this->getNumLevels()) {
				$scale = $this->getScale($level);
				$width = (int)ceil($this->_width * $scale);
				$height = (int)ceil($this->_height * $scale);
				$this->_dimensions[$key] = array('width' => $width,'height' => $height);
			} else throw new Exception("Invalid pyramid level (dimension)");
		}
		 
		return $this->_dimensions[$key];
	} 
	
    /**
	 * Number of tiles (columns, rows)
	 *
	 * @param int $level
	 * @return array
	 * @throw Deepzoom\Exception check pyramid level
	 */
	public function getNumTiles($level) {
		if(0 <= $level and $level < $this->getNumLevels()) {
			$dimension = $this->getDimension($level);
			$columns = (int)ceil(floatval($dimension['width']) / $this->_tileSize);
			$rows = (int)ceil(floatval($dimension['height']) / $this->_tileSize);
			
			return array('columns' => $columns, 'rows' => $rows);
		} else throw new Exception("Invalid pyramid level (NumTiles)");
	}
	
	 /**
     * Bounding box of the tile (x1, y1, width, height)
     * 
     * @param int $level   pyramid level
     * @param int $column
     * @param int $row
     * 
     * @return array (x,y,width,height)
     * @throw Deepzoom\Exception check pyramid level
     */ 
    public function getTileBounds($level, $column, $row) {
    	 if(0 <= $level and $level < $this->getNumLevels()) {
    	 	$position = $this->getTileBoundsPosition($column, $row);
    	 	
			$dimension = $this->getDimension($level);
			$width = $this->_tileSize + ($column == 0 ? 1 : 2) * $this->_tileOverlap;
			$height = $this->_tileSize + ($row == 0 ? 1 : 2) * $this->_tileOverlap;
			$newWidth = min($width, $dimension['width'] - $position['x']);
			$newHeight = min($height, $dimension['height'] - $position['y']);
			
			return array_merge($position,array( 'width' => $newWidth,'height' => $newHeight));
		} else throw new Exception("Invalid pyramid level (TileBounds)");
    } 
    
    /**
     * Return Tile Bound Position
     * 
     * @param int $column
     * @param int $row
     * 
     * @return array 
     */
    protected function getTileBoundsPosition($column, $row) {
    	$offsetX = $column == 0 ? 0 : $this->_tileOverlap;
        $offsetY = $row == 0 ? 0 : $this->_tileOverlap;
        $x = ($column * $this->_tileSize) - $offsetX;
        $y = ($row * $this->_tileSize) - $offsetY;
        
        return array('x' => $x, 'y' => $y);
    }
    
	/**
     * Gets the width of the image.
     *
     * @return int The image width
     */
    public function getWidth()
    {
        return $this->_width;
    }

    /**
     * Sets the image width
     *
     * @param int $width The image width
     */
    public function setWidth($width)
    {
        $this->_width = $width;
        
        return $this;
    }
    
	/**
     * Gets the height of the image.
     *
     * @return int The image height
     */
    public function getHeight()
    {
        return $this->_height;
    }

    /**
     * Sets the image height
     *
     * @param int $height The image height
     */
    public function setHeight($height)
    {
        $this->_height = $height;
        
        return $this;
    }
    
	/**
     * Gets the size of the tile.
     *
     * @return int The tile size
     */
    public function getTileSize()
    {
        return $this->_tileSize;
    }

    /**
     * Sets the tile size
     *
     * @param int $tileSize The tile size
     */
    public function setTileSize($tileSize)
    {
        $this->_tileSize = $tileSize;
        
        return $this;
    }
    
	/**
     * Gets the overlap of the tile.
     *
     * @return int The tile overlap
     */
    public function getTileOverlap()
    {
        return $this->_tileOverlap;
    }

    /**
     * Sets the tile overlap
     *
     * @param int $tileOverlap The tile overlap
     */
    public function setTileOverlap($tileOverlap)
    {
        $this->_tileOverlap = $tileOverlap;
        
        return $this;
    }
    
	/**
     * Gets the format of the tile.
     *
     * @return int The format overlap
     */
    public function getTileFormat()
    {
        return $this->_tileFormat;
    }

    /**
     * Sets the format overlap
     *
     * @param string $tileOverlap The format overlap
     */
    public function setTileFormat($tileFormat)
    {
        $this->_tileFormat = $tileFormat;
        
        return $this;
    }    
    
    
    protected function startXml()
    {
        return <<<EOF
<?xml version="1.0" encoding="UTF-8"?>

EOF;
    }
    
    protected function endXml()
    {
        return "</Image>";
    }
    
    protected function addTileParameters() {
    	return sprintf("<Image TileSize=\"%s\" Overlap=\"%s\" Format=\"%s\" xmlns=\"http://schemas.microsoft.com/deepzoom/2008\">\n",
    			$this->_tileSize,$this->_tileOverlap,$this->_tileFormat);
    }
    
	protected function addImageParameters() {
    	return sprintf("\t<Size Width=\"%s\" Height=\"%s\"/>\n",$this->_width,$this->_height);
    }
    
    
    /**
     * 
     * @param string $key
     */
    protected function getCachedDimension($key){
        if(!isset($this->_dimensions[$key])) {
            return false;
        }
        
        return $this->_dimensions[$key];
    }
    
    /**
     * 
     * @param int $level
     */
    protected function getDimensionKey($level) {
        return md5($level.$this->_width.$this->_height);
    }
    
}