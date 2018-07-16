<?php

namespace Fichat\Utils;

include APP_DIR .'/lib/emoji/emoji.php';

class Emoji
{
    /**
     * 将表情转成对应代表字符串
     *
     * @param string $content
     * @return mixed|string
     */
    public static function emojiEncode($content = '')
    {
        $content = json_encode($content);
        $content = emoji_softbank_to_unified($content);
        return json_decode($content, true);
    }

    /**
     * 将对应字符串转成表情
     *
     * @param string $content
     * @return mixed|string
     */
    public static function emojiDecode($content = '')
    {
        $content = json_encode($content);
        $content = emoji_unified_to_softbank($content);
        return json_decode($content, true);
    }
}