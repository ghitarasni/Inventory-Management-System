<?php
/**
 * Helper Escape: Melindungi dari serangan Cross-Site Scripting (XSS)
 */

function e($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}
