<?php
/**
 * Simple session-based rate limiter
 * Prevents abuse of API endpoints
 */
class RateLimiter {
    private $limit;
    private $window;
    private $key;

    public function __construct($key, $limit = 10, $window = 60) {
        $this->limit = $limit;
        $this->window = $window;
        $this->key = 'rl_' . md5($key);
    }

    public function allow() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $now = time();
        if (!isset($_SESSION[$this->key])) {
            $_SESSION[$this->key] = ['count' => 1, 'start' => $now];
            return true;
        }

        $data = $_SESSION[$this->key];
        $elapsed = $now - $data['start'];

        if ($elapsed > $this->window) {
            $_SESSION[$this->key] = ['count' => 1, 'start' => $now];
            return true;
        }

        if ($data['count'] < $this->limit) {
            $_SESSION[$this->key]['count']++;
            return true;
        }

        return false; // Limit exceeded
    }

    public function remaining() {
        if (!isset($_SESSION[$this->key])) return $this->limit;
        return max(0, $this->limit - $_SESSION[$this->key]['count']);
    }
}
