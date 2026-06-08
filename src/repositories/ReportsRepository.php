<?php

require_once 'Repository.php';

class ReportsRepository extends Repository {
    public function createReport(int $reporterId, int $reportedUserId, string $reason): void
    {
        $reason = trim($reason) !== '' ? trim($reason) : 'Reported from profile';
        $reason = substr($reason, 0, 255);

        $query = $this->database->connect()->prepare(
            "
            INSERT INTO user_reports (reporter_id, reported_user_id, reason)
            VALUES (:reporter_id, :reported_user_id, :reason)
            ON CONFLICT (reporter_id, reported_user_id) DO UPDATE SET
                reason = EXCLUDED.reason,
                status = 'OPEN',
                reviewed_by = NULL,
                reviewed_at = NULL,
                action_note = NULL,
                updated_at = CURRENT_TIMESTAMP
            "
        );
        $query->execute([
            'reporter_id' => $reporterId,
            'reported_user_id' => $reportedUserId,
            'reason' => $reason,
        ]);
    }

    public function countOpen(): int
    {
        return $this->countByFilter('open');
    }

    public function countResolved(): int
    {
        return $this->countByFilter('resolved');
    }

    public function countBanned(): int
    {
        return $this->countByFilter('banned');
    }

    private function countByFilter(string $filter): int
    {
        $where = match ($filter) {
            'resolved' => "r.status = 'RESOLVED'",
            'banned' => "u.is_active = FALSE",
            default => "r.status = 'OPEN'",
        };

        $query = $this->database->connect()->prepare(
            "
            SELECT COUNT(DISTINCT r.reported_user_id)
            FROM user_reports r
            JOIN users u ON u.id = r.reported_user_id
            WHERE {$where}
            "
        );
        $query->execute();

        return (int) $query->fetchColumn();
    }

    public function getOpenReportedUsers(int $limit = 12): array
    {
        return $this->getReportedUsers('open', $limit);
    }

    public function getReportedUsers(string $filter = 'open', int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $filter = in_array($filter, ['open', 'resolved', 'banned'], true) ? $filter : 'open';
        $where = match ($filter) {
            'resolved' => "r.status = 'RESOLVED'",
            'banned' => "u.is_active = FALSE",
            default => "r.status = 'OPEN'",
        };
        $latestStatus = $filter === 'banned' ? '' : "AND latest.status = '" . strtoupper($filter) . "'";

        $query = $this->database->connect()->prepare(
            "
            SELECT
                r.reported_user_id,
                COUNT(*) AS report_count,
                MAX(r.created_at) AS last_reported_at,
                MAX(r.reviewed_at) AS last_reviewed_at,
                (
                    SELECT reason
                    FROM user_reports latest
                    WHERE latest.reported_user_id = r.reported_user_id
                      {$latestStatus}
                    ORDER BY latest.created_at DESC
                    LIMIT 1
                ) AS latest_reason,
                (
                    SELECT action_note
                    FROM user_reports latest
                    WHERE latest.reported_user_id = r.reported_user_id
                      AND latest.action_note IS NOT NULL
                    ORDER BY latest.reviewed_at DESC NULLS LAST, latest.updated_at DESC
                    LIMIT 1
                ) AS latest_action_note,
                u.email,
                COALESCE(u.display_name, u.firstname) AS display_name,
                u.role,
                u.is_active
            FROM user_reports r
            JOIN users u ON u.id = r.reported_user_id
            WHERE {$where}
            GROUP BY r.reported_user_id, u.email, u.display_name, u.firstname, u.role, u.is_active
            ORDER BY last_reported_at DESC, report_count DESC
            LIMIT {$limit}
            "
        );
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportsForUser(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                r.id,
                r.reason,
                r.status,
                r.created_at,
                r.reviewed_at,
                r.action_note,
                reviewer.email AS reviewer_email,
                COALESCE(reviewer.display_name, reviewer.firstname) AS reviewer_name,
                reporter.email AS reporter_email,
                COALESCE(reporter.display_name, reporter.firstname) AS reporter_name
            FROM user_reports r
            JOIN users reporter ON reporter.id = r.reporter_id
            LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
            WHERE r.reported_user_id = :user_id
            ORDER BY r.created_at DESC
            "
        );
        $query->execute(['user_id' => $userId]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resolveOpenReportsForUser(int $reportedUserId, int $adminId, string $note): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE user_reports
            SET status = 'RESOLVED',
                reviewed_by = :admin_id,
                reviewed_at = CURRENT_TIMESTAMP,
                action_note = :note,
                updated_at = CURRENT_TIMESTAMP
            WHERE reported_user_id = :reported_user_id
              AND status = 'OPEN'
            "
        );
        $query->execute([
            'admin_id' => $adminId,
            'note' => substr($note, 0, 255),
            'reported_user_id' => $reportedUserId,
        ]);
    }

    public function hasOpenReportsForUser(int $reportedUserId): bool
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT 1
            FROM user_reports
            WHERE reported_user_id = :reported_user_id
              AND status = 'OPEN'
            LIMIT 1
            "
        );
        $query->execute(['reported_user_id' => $reportedUserId]);

        return (bool) $query->fetchColumn();
    }
}
