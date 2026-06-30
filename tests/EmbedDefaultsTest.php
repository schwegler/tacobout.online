<?php

use PHPUnit\Framework\TestCase;

class EmbedDefaultsTest extends TestCase {

    public function test_tacobout_embed_defaults() {
        // Arrange
        $initial_defaults = array(
            'width' => 800,
            'height' => 600,
            'other_param' => 'value'
        );

        // Act
        $modified_defaults = tacobout_embed_defaults( $initial_defaults );

        // Assert
        $this->assertEquals( 900, $modified_defaults['width'] );
        $this->assertEquals( 506, $modified_defaults['height'] );
        $this->assertEquals( 'value', $modified_defaults['other_param'] );
    }

    public function test_tacobout_embed_defaults_empty_array() {
        // Arrange
        $initial_defaults = array();

        // Act
        $modified_defaults = tacobout_embed_defaults( $initial_defaults );

        // Assert
        $this->assertEquals( 900, $modified_defaults['width'] );
        $this->assertEquals( 506, $modified_defaults['height'] );
    }
}
