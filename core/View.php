<?php
/**
 * View renderer — layouts + partials.
 */

defined('BASE_PATH') or die('Direct access denied');

class View
{
    /**
     * View ને main layout માં render કરો.
     *
     * @param string $view views/ માંનો relative path ('.php' વગર)
     * @param array  $data view માં extract થતાં variables
     */
    public static function render(string $view, array $data = []): void
    {
        $data['content'] = self::partial($view, $data);
        extract($data, EXTR_SKIP);
        include VIEWS_PATH . '/layouts/main.php';
    }

    /**
     * Partial/view ને string તરીકે render કરો (layout વગર).
     */
    public static function partial(string $view, array $data = []): string
    {
        $file = VIEWS_PATH . '/' . $view . '.php';
        if (!is_file($file)) {
            Logger::error("View not found: {$view}");
            return '';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
