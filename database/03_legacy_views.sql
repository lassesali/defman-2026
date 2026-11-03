-- =============================================================================
-- File: 03_legacy_views.sql
-- Description: Backward-Compatibility Read Views (`raport`, `analyse`, `product`)
--              Reconstructs exact legacy 2004 column names and formats from 3NF
--              IEEE Std 1044-2009 and DORA telemetry tables so legacy queries
--              still resolve without error.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. View: `product` (compatibility alias for `products`)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `product` AS
SELECT
  p.`id`,
  p.`name`,
  p.`created_at`
FROM `products` p;

-- -----------------------------------------------------------------------------
-- 2. View: `raport` (reconstructs legacy `raport` format from `defect_reports`)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `raport` AS
SELECT
  dr.`id` AS `ID`,
  p.`name` AS `Product`,
  dr.`product_id` AS `product_id`,
  dr.`environment` AS `Environment`,
  dr.`reporter_user_id` AS `CustomerID`,
  COALESCE(u.`login`, dr.`guest_name`, '') AS `Who`,
  COALESCE(u.`email`, dr.`guest_email`, '') AS `Contactemail`,
  DATE_FORMAT(dr.`reported_at`, '%Y-%m-%d %H:%i') AS `Whenreported`,
  dr.`title` AS `Title`,
  dr.`description` AS `Description`,
  dr.`discovered_by` AS `Discoveredby`,
  dr.`person_type` AS `Persontype`,
  dr.`occurred_on` AS `Occured`,
  dr.`location` AS `Location`,
  dr.`initial_severity` AS `Severity`,
  CASE dr.`initial_priority`
    WHEN 'most_urgent' THEN 'm_urgent'
    WHEN 'average'     THEN 'average'
    WHEN 'less_urgent' THEN 'l_urgent'
  END AS `Priority`,
  DATE(dr.`reported_at`) AS `Submitted`,
  dr.`additional_info` AS `Addinfo`
FROM `defect_reports` dr
INNER JOIN `products` p
  ON p.`id` = dr.`product_id`
LEFT JOIN `users` u
  ON u.`id` = dr.`reporter_user_id`;

-- -----------------------------------------------------------------------------
-- 3. View: `analyse` (reconstructs legacy `analyse` format from `defect_analyses`)
--    Maps IEEE 1044-2009 `root_cause_type`, `actual_effort_hours`, `triaged_at`,
--    and `resolved_at` seamlessly back to legacy 2004 column aliases.
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `analyse` AS
SELECT
  da.`report_id` AS `id`,
  da.`developer_description` AS `devdesc`,
  COALESCE(da.`root_cause_type`, fc.`code`) AS `classification`,
  da.`date_originated` AS `dateoriginated`,
  ph.`phases_csv` AS `phase`,
  da.`activity_originated` AS `actoriginated`,
  da.`deliverable` AS `deliverable`,
  da.`what_to_do` AS `whattodo`,
  DATE(dr.`reported_at`) AS `submitted`,
  COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)) AS `assigned`,
  CASE da.`uniqueness`
    WHEN 'duplicate' THEN 'Already submitted'
    WHEN 'new'       THEN 'New defect'
  END AS `uniqueness`,
  CASE da.`priority`
    WHEN 'most_urgent' THEN 'most urgent'
    WHEN 'average'     THEN 'average'
    WHEN 'less_urgent' THEN 'less urgent'
  END AS `priority`,
  da.`severity` AS `severity`,
  da.`workaround` AS `workaround`,
  da.`proposed_fix` AS `proposedfix`,
  da.`status` AS `status`,
  da.`fix_description` AS `fixdesc`,
  da.`fix_started_on` AS `fixstarted`,
  COALESCE(da.`fix_completed_on`, DATE(da.`resolved_at`)) AS `fixcomplete`,
  da.`fix_impact` AS `fiximpact`,
  COALESCE(da.`actual_effort_hours`, da.`fix_effort`) AS `fixeffort`,
  da.`fix_cost` AS `fixcost`,
  da.`resolution_description` AS `resodesc`,
  COALESCE(da.`resolution_completed_on`, DATE(da.`resolved_at`)) AS `resocomplete`,
  da.`resolution_closed_on` AS `resoclosed`,
  da.`cost_of_defect` AS `costofdefect`,
  da.`activity_found` AS `actfound`
FROM `defect_analyses` da
INNER JOIN `defect_reports` dr
  ON dr.`id` = da.`report_id`
LEFT JOIN `fault_classifications` fc
  ON fc.`id` = da.`classification_id`
LEFT JOIN (
  SELECT
    ap.`report_id`,
    GROUP_CONCAT(sp.`name` ORDER BY sp.`id` SEPARATOR ',') AS `phases_csv`
  FROM `analysis_phases` ap
  INNER JOIN `sdlc_phases` sp
    ON sp.`id` = ap.`phase_id`
  GROUP BY ap.`report_id`
) ph
  ON ph.`report_id` = da.`report_id`;
