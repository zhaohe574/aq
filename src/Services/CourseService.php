<?php

namespace App\Services;

use App\Core\Api;
use App\Core\Logger;

class CourseService {
    private Api $api;
    private Logger $logger;
    
    public function __construct(Api $api, Logger $logger) {
        $this->api = $api;
        $this->logger = $logger;
    }
    
    public function getCourses(): array {
        $response = $this->api->post('app/trainCoursePlan/queryAppTCPList', [
            'isFinished' => '0',
            'appPageNum' => 1,
            'appPageSize' => 10,
            'total' => 0
        ]);
        
        if (isset($response['code']) && $response['code'] === 500) {
            $this->logger->log("Failed to get courses: " . ($response['msg'] ?? 'Unknown error'), 'error');
            return [];
        }
        
        return [
            'total' => $response['total'] ?? 0,
            'courses' => $response['rows'] ?? []
        ];
    }
    
    // TODO: Implement course processing methods as they become available
    public function processCourse(array $course): bool {
        $this->logger->log("Course processing not implemented yet", 'info');
        return false;
    }
} 