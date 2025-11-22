<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Messages;


/**
 * Class SlackAttachment
 *
 * Core class for the Messages layer providing essential functionality for
 * the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Messages
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class SlackAttachment
{
    public string $title = '';
    public string $text = '';
    public string $color = 'good';
    public array $fields = [];

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function fields(array $fields): self
    {
        foreach ($fields as $key => $value) {
            $this->fields[] = [
                'title' => $key,
                'value' => $value,
                'short' => true
            ];
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'text' => $this->text,
            'color' => $this->color,
            'fields' => $this->fields,
        ]);
    }
}
