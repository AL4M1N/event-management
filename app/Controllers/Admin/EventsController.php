<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use App\Helpers\Helper;
use PDO;


class EventsController
{
    private $eventModel;

    private const PER_PAGE = 2;
    private const MIN_NAME_LENGTH = 3;
    private const MAX_NAME_LENGTH = 50;
    private const MIN_DESCRIPTION_LENGTH = 10;

    public function __construct(?Event $eventModel = null, ?PDO $pdo = null)
    {
        $this->eventModel = $eventModel;
        $this->pdo = $pdo;
    }

    public function index()
    {
        try {
            $currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
            $offset = ($currentPage - 1) * self::PER_PAGE;
            
            $filters = $this->buildSearchFilters();
            $totalRecords = $this->getTotalRecords($filters);
            $totalPages = ceil($totalRecords / self::PER_PAGE);
            
            list($sortColumn, $sortOrder) = $this->getSortingParams();
            $events = $this->eventModel->getFilteredEvents(
                $filters,
                $offset,
                self::PER_PAGE,
                $sortColumn,
                $sortOrder
            );

            return Helper::view('backend/events/index.php', [
                'events' => $events,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'perPage' => self::PER_PAGE,
                'totalRecords' => $totalRecords
            ]);
        } catch (\PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return $this->handleError();
        }
    }

    private function buildSearchFilters(): array
    {
        $filters = [];
        
        if ($name = trim(filter_input(INPUT_GET, 'name'))) {
            $filters['name'] = ['AND e.name LIKE :name', "%{$name}%"];
        }
        if ($date = trim(filter_input(INPUT_GET, 'date'))) {
            $filters['date'] = ['AND DATE(e.date) = :date', $date];
        }
        if ($capacity = filter_input(INPUT_GET, 'capacity', FILTER_VALIDATE_INT)) {
            $filters['capacity'] = ['AND e.capacity >= :capacity', $capacity];
        }
        
        return $filters;
    }

    private function getTotalRecords($filters)
    {
        return $this->eventModel->getTotalFilteredRecords($filters);
    }

    private function getSortingParams()
    {
        $allowedSortColumns = ['name', 'date', 'capacity'];
        $sortColumn = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'date';
        $sortOrder = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';
        return [$sortColumn, $sortOrder];
    }

    private function handleError()
    {
        $data = [
            'error' => "An error occurred while fetching the events.",
            'events' => [],
            'current_page' => 1,
            'total_pages' => 1,
            'per_page' => 2,
            'total_records' => 0
        ];
        require_once __DIR__ . '/../../Views/backend/events/index.php';
    }

    public function showEventForm()
    {
        $error = '';
        $success = '';
        $formData = [];
        require_once __DIR__ . '/../../Views/backend/events/create.php';
    }

    public function store()
    {
        if (!Helper::isPostRequest()) {
            return Helper::redirect('events/new');
        }

        $formData = $this->sanitizeEventData($_POST);
        $errors = $this->validateEventData($formData);

        if (!empty($errors)) {
            return Helper::view('backend/events/create.php', [
                'error' => 'Please correct the errors below.',
                'errors' => $errors,
                'formData' => $formData
            ]);
        }

        try {
            $result = $this->eventModel->createEvent($formData);
            if ($result) {
                return Helper::redirect('events', [
                    'success' => 'Event created successfully!'
                ]);
            }
        } catch (\Exception $e) {
            return Helper::view('backend/events/create.php', [
                'error' => 'An error occurred while saving the event.',
                'formData' => $formData
            ]);
        }
    }

    private function sanitizeEventData(array $data): array
    {
        return array_map('trim', [
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'date' => $data['date'] ?? '',
            'capacity' => $data['capacity'] ?? ''
        ]);
    }

    private function validateEventData(array $data): array
    {
        $errors = [];

        if (strlen($data['name']) < self::MIN_NAME_LENGTH || strlen($data['name']) > self::MAX_NAME_LENGTH) {
            $errors['name'] = sprintf('Event name must be between %d and %d characters.', 
                self::MIN_NAME_LENGTH, self::MAX_NAME_LENGTH);
        }

        if (strlen($data['description']) < self::MIN_DESCRIPTION_LENGTH) {
            $errors['description'] = 'Description must be at least ' . self::MIN_DESCRIPTION_LENGTH . ' characters long.';
        }

        if (!is_numeric($data['capacity']) || $data['capacity'] <= 0) {
            $errors['capacity'] = 'Capacity must be a positive number.';
        }

        if (empty($data['date'])) {
            $errors['date'] = 'Please select a valid date.';
        } else {
            try {
                $selectedDate = new \DateTime($data['date']);
                $today = new \DateTime();
                $today->setTime(0, 0, 0);

                if ($selectedDate < $today) {
                    $errors['date'] = 'Date cannot be in the past.';
                }
            } catch (\Exception $e) {
                $errors['date'] = 'Invalid date format.';
            }
        }

        return $errors;
    }

    public function showEditEventForm($id)
    {
        $event = $this->eventModel->getEventById($id);
        require __DIR__ . '/../../Views/backend/events/edit.php';
    }

    public function update($id)
    {
        if (!Helper::isPostRequest()) {
            return Helper::redirect('events/edit/' . $id);
        }

        $formData = $this->sanitizeEventData($_POST);
        $errors = $this->validateEventData($formData);
        $event = $this->eventModel->getEventById($id);
        $error = null;
        $success = null;

        if (!empty($errors)) {
            $error = 'Please correct the errors below.';
            return Helper::view('backend/events/edit.php', 
                compact('error', 'errors', 'event', 'formData')
            );
        }

        $result = $this->eventModel->update($id, $formData);

        if ($result) {
            $success = 'Event updated successfully! <a href="' . Helper::baseUrl('events') . '">View Events</a>';
        } else {
            $error = 'An error occurred while updating the event.';
        }

        return Helper::view('backend/events/edit.php', 
            compact('success', 'error', 'event', 'formData')
        );
    }

    public function delete($id)
    {
        $this->eventModel->delete($id);
        return Helper::redirect('events');
    }

    public function exportEventCsv($id)
    {
        $event = $this->eventModel->getEventById($id);
        if (!$event) {
            header('Location: ' . base_url('events'));
            exit;
        }

        $attendees = $this->eventModel->getEventAttendeesForExport($id);
        
        // Set headers for CSV download
        $filename = 'event_' . $id . '_report_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for proper Excel encoding
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write event details
        fputcsv($output, ['Event Details']);
        fputcsv($output, ['Name', $event['name']]);
        fputcsv($output, ['Description', $event['description']]);
        fputcsv($output, ['Date', $event['date']]);
        fputcsv($output, ['Capacity', $event['capacity']]);
        fputcsv($output, []);  // Empty line for spacing

        // Write attendees header
        fputcsv($output, ['Attendees List']);
        fputcsv($output, ['No.', 'Name', 'Phone', 'NID', 'Registration Date']);

        // Write attendees data
        $counter = 1;
        foreach ($attendees as $attendee) {
            fputcsv($output, [
                $counter++,
                $attendee['name'],
                $attendee['phone'],
                $attendee['nid'],
                $attendee['registration_date']
            ]);
        }

        fclose($output);
        exit;
    }

    public function getEventDetailsApi($id)
    {
        $event = $this->eventModel->getEventById($id);
        
        if (!$event) {
            Helper::jsonResponse(['error' => 'Event not found'], 404);
        }

        $response = [
            'name' => $event['name'],
            'description' => $event['description'],
            'date' => $event['date'],
            'capacity' => $event['capacity'],
            'attendees_count' => $this->eventModel->getAttendeesCount($id)
        ];

        Helper::jsonResponse($response);
    }
}
