<?php

function ui_icon(string $name, string $class = 'h-5 w-5'): string
{
    $icons = [
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12 11.204 3.046a1.125 1.125 0 0 1 1.592 0L21.75 12M4.5 9.75V19.5A1.5 1.5 0 0 0 6 21h3.75v-6.75h4.5V21H18a1.5 1.5 0 0 0 1.5-1.5V9.75" />',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 8.25h18M4.5 6.75h15A1.5 1.5 0 0 1 21 8.25v10.5A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25a1.5 1.5 0 0 1 1.5-1.5Z" />',
        'check-circle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        'banknotes' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75h19.5v10.5H2.25V6.75Zm3 3.75h.008v.008H5.25V10.5Zm13.5 0h.008v.008h-.008V10.5Zm-13.5 3h.008v.008H5.25V13.5Zm13.5 0h.008v.008h-.008V13.5ZM9 9.75h6v4.5H9v-4.5Z" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
        'pencil' => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487Z" />',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M4.772 5.79a48.108 48.108 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0C9.16 2.313 8.25 3.297 8.25 4.477v.916m7.5 0a48.667 48.667 0 0 0-7.5 0m7.5 0H21m-12.75 0H3m1.5 0 .66 12.135A2.25 2.25 0 0 0 7.404 21h9.192a2.25 2.25 0 0 0 2.244-2.115L19.5 5.79" />',
        'arrow-up-tray' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 9 12 4.5m0 0L16.5 9M12 4.5V16.5" />',
        'printer' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2.25h12V9M6 18h12v3.75H6V18Zm-2.25-6.75h16.5A1.5 1.5 0 0 1 21.75 12.75v3A1.5 1.5 0 0 1 20.25 17.25H18V15H6v2.25H3.75A1.5 1.5 0 0 1 2.25 15.75v-3a1.5 1.5 0 0 1 1.5-1.5Z" />',
        'eye' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.433 0 .644C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
        'eye-slash' => '<path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.477 10.488A3 3 0 0 0 13.5 13.5m2.09 2.122A9.956 9.956 0 0 1 12 16.5c-4.638 0-8.573-3.007-9.964-7.178a1.012 1.012 0 0 1 0-.644 9.968 9.968 0 0 1 4.307-5.247m3.249-1.147A10.048 10.048 0 0 1 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.433 0 .644a9.985 9.985 0 0 1-1.684 2.908M14.121 14.121 9.88 9.88" />',
    ];

    $path = $icons[$name] ?? $icons['home'];
    return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="' . e($class) . '">' . $path . '</svg>';
}
