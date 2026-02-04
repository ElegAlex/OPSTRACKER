# Routes OpsTracker

Documentation des routes de l'application.

---

## Authentification

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_login` | GET | `/login` | `SecurityController::login` |
| `app_logout` | GET | `/logout` | `SecurityController::logout` |
| `app_home` | GET | `/` | `HomeController::index` |

---

## Administration EasyAdmin

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `admin` | GET | `/admin` | `Admin\DashboardController::index` |

> Note: Les routes CRUD EasyAdmin sont generees automatiquement via `?crudAction=xxx&crudControllerFqcn=xxx`

### Controllers Admin CRUD

- `CampagneCrudController` - Gestion des campagnes
- `TypeOperationCrudController` - Types d'operation
- `ChecklistTemplateCrudController` - Templates checklist
- `UtilisateurCrudController` - Utilisateurs
- `AgentCrudController` - Agents (reservation V2)

### Configuration Admin

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `admin_configuration_index` | GET | `/admin/configuration` | `Admin\ConfigurationController::index` |
| `admin_configuration_export` | GET | `/admin/configuration/export` | `Admin\ConfigurationController::export` |
| `admin_configuration_import` | POST | `/admin/configuration/import` | `Admin\ConfigurationController::import` |

---

## Campagnes (Sophie - Gestionnaire IT)

### Portfolio & CRUD Campagne

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_campagne_index` | GET | `/campagnes` | `CampagneController::index` |
| `app_campagne_new` | GET, POST | `/campagnes/nouvelle` | `CampagneController::new` |
| `app_campagne_step2` | GET, POST | `/campagnes/{id}/import` | `CampagneController::step2` |
| `app_campagne_step3` | GET, POST | `/campagnes/{id}/mapping` | `CampagneController::step3` |
| `app_campagne_step4` | GET, POST | `/campagnes/{id}/configurer` | `CampagneController::step4` |
| `app_campagne_show` | GET | `/campagnes/{id}` | `CampagneController::show` |
| `app_campagne_operation_new` | GET, POST | `/campagnes/{id}/operations/nouvelle` | `CampagneController::newOperation` |
| `app_campagne_transition` | POST | `/campagnes/{id}/transition/{transition}` | `CampagneController::transition` |
| `app_campagne_delete` | POST | `/campagnes/{id}/supprimer` | `CampagneController::delete` |
| `app_campagne_export` | GET | `/campagnes/{id}/export` | `CampagneController::export` |
| `app_campagne_proprietaire` | GET, POST | `/campagnes/{id}/proprietaire` | `CampagneController::transfertProprietaire` |
| `app_campagne_visibilite` | GET, POST | `/campagnes/{id}/visibilite` | `CampagneController::visibilite` |

---

## Operations

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_operation_index` | GET | `/campagnes/{campagne}/operations` | `OperationController::index` |
| `app_operation_export` | GET | `/campagnes/{campagne}/operations/export.csv` | `OperationController::exportCsv` |
| `app_operation_show` | GET | `/campagnes/{campagne}/operations/{id}` | `OperationController::show` |
| `app_operation_edit` | GET, POST | `/campagnes/{campagne}/operations/{id}/modifier` | `OperationController::edit` |
| `app_operation_transition` | POST | `/campagnes/{campagne}/operations/{id}/transition/{transition}` | `OperationController::transition` |
| `app_operation_assigner` | POST | `/campagnes/{campagne}/operations/{id}/assigner` | `OperationController::assigner` |
| `app_operation_segment` | POST | `/campagnes/{campagne}/operations/{id}/segment` | `OperationController::assignerSegment` |
| `app_operation_update_statut` | POST | `/campagnes/{campagne}/operations/{id}/statut` | `OperationController::updateStatut` |

---

## Segments

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_segment_index` | GET | `/campagnes/{campagne}/segments` | `SegmentController::index` |
| `app_segment_new` | GET, POST | `/campagnes/{campagne}/segments/nouveau` | `SegmentController::new` |
| `app_segment_show` | GET | `/campagnes/{campagne}/segments/{id}` | `SegmentController::show` |
| `app_segment_edit` | GET, POST | `/campagnes/{campagne}/segments/{id}/modifier` | `SegmentController::edit` |
| `app_segment_delete` | POST | `/campagnes/{campagne}/segments/{id}/supprimer` | `SegmentController::delete` |

---

## Dashboard (KPIs & Progression)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_dashboard_global` | GET | `/dashboard` | `DashboardController::global` |
| `app_dashboard_campagne` | GET | `/dashboard/campagne/{id}` | `DashboardController::campagne` |
| `app_dashboard_segment` | GET | `/dashboard/campagne/{id}/segment/{segmentId}` | `DashboardController::segment` |
| `app_dashboard_refresh` | GET | `/dashboard/campagne/{id}/refresh` | `DashboardController::refresh` |
| `app_dashboard_widget` | GET | `/dashboard/campagne/{id}/widget/{widget}` | `DashboardController::widget` |
| `app_dashboard_activite` | GET | `/dashboard/campagne/{id}/activite` | `DashboardController::activite` |
| `app_dashboard_segments` | GET | `/dashboard/campagne/{id}/segments` | `DashboardController::segments` |
| `app_dashboard_export_pdf` | GET | `/dashboard/campagne/{id}/export-pdf` | `DashboardController::exportPdf` |

---

## Prerequis

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_prerequis_index` | GET | `/campagne/{campagneId}/prerequis` | `PrerequisController::index` |
| `app_prerequis_global_new` | GET, POST | `/campagne/{campagneId}/prerequis/global/new` | `PrerequisController::newGlobal` |
| `app_prerequis_segment_new` | GET, POST | `/campagne/{campagneId}/prerequis/segment/{segmentId}/new` | `PrerequisController::newSegment` |
| `app_prerequis_edit` | GET, POST | `/campagne/{campagneId}/prerequis/{id}/edit` | `PrerequisController::edit` |
| `app_prerequis_statut` | POST | `/campagne/{campagneId}/prerequis/{id}/statut` | `PrerequisController::changeStatut` |
| `app_prerequis_delete` | POST | `/campagne/{campagneId}/prerequis/{id}/delete` | `PrerequisController::delete` |
| `app_prerequis_row` | GET | `/campagne/{campagneId}/prerequis/{id}/row` | `PrerequisController::row` |

---

## Creneaux (Planification)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_creneau_index` | GET | `/campagnes/{campagne}/creneaux` | `CreneauController::index` |
| `app_creneau_new` | GET, POST | `/campagnes/{campagne}/creneaux/nouveau` | `CreneauController::new` |
| `app_creneau_generate` | GET, POST | `/campagnes/{campagne}/creneaux/generer` | `CreneauController::generate` |
| `app_creneau_edit` | GET, POST | `/campagnes/{campagne}/creneaux/{id}/modifier` | `CreneauController::edit` |
| `app_creneau_delete` | POST | `/campagnes/{campagne}/creneaux/{id}/supprimer` | `CreneauController::delete` |
| `app_creneau_duplicate` | POST | `/campagnes/{campagne}/creneaux/{id}/dupliquer` | `CreneauController::duplicate` |

---

## Terrain (Karim - Technicien IT)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `terrain_index` | GET | `/terrain` | `TerrainController::index` |
| `terrain_show` | GET | `/terrain/{id}` | `TerrainController::show` |
| `terrain_checklist_toggle` | POST | `/terrain/{id}/checklist/toggle/{etapeId}` | `TerrainController::toggleEtape` |
| `terrain_document_view` | GET | `/terrain/{id}/document/{documentId}` | `TerrainController::viewDocument` |
| `terrain_document_download` | GET | `/terrain/{id}/document/{documentId}/download` | `TerrainController::downloadDocument` |
| `terrain_transition` | POST | `/terrain/{id}/transition/{transition}` | `TerrainController::transition` |
| `terrain_demarrer` | POST | `/terrain/{id}/demarrer` | `TerrainController::demarrer` |
| `terrain_terminer` | POST | `/terrain/{id}/terminer` | `TerrainController::terminer` |
| `terrain_reporter` | POST | `/terrain/{id}/reporter` | `TerrainController::reporter` |
| `terrain_probleme` | POST | `/terrain/{id}/probleme` | `TerrainController::probleme` |

---

## Reservation Agent (Acces par Token)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_booking_index` | GET | `/reservation/{token}` | `BookingController::index` |
| `app_booking_select` | POST | `/reservation/{token}/choisir/{creneau}` | `BookingController::select` |
| `app_booking_confirm` | GET | `/reservation/{token}/confirmer` | `BookingController::confirm` |
| `app_booking_cancel` | POST | `/reservation/{token}/annuler` | `BookingController::cancel` |
| `app_booking_modify` | GET, POST | `/reservation/{token}/modifier` | `BookingController::modify` |
| `app_booking_recap` | GET | `/reservation/{token}/recapitulatif` | `BookingController::recap` |
| `app_booking_ics` | GET | `/reservation/{token}/ics` | `BookingController::downloadIcs` |
| `app_booking_sms_optin` | GET, POST | `/reservation/{token}/sms` | `BookingController::smsOptin` |

---

## Manager (Positionnement Equipe)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_manager_agents` | GET | `/manager/campagne/{campagne}/agents` | `ManagerBookingController::agents` |
| `app_manager_position` | GET, POST | `/manager/campagne/{campagne}/positionner/{agent}` | `ManagerBookingController::position` |
| `app_manager_modify` | GET, POST | `/manager/campagne/{campagne}/modifier/{reservation}` | `ManagerBookingController::modify` |
| `app_manager_cancel` | POST | `/manager/campagne/{campagne}/annuler/{reservation}` | `ManagerBookingController::cancel` |
| `app_manager_planning` | GET | `/manager/campagne/{campagne}/planning` | `ManagerBookingController::planning` |
| `app_manager_calendar` | GET | `/manager/campagne/{campagne}/calendar` | `ManagerCalendarController::index` |
| `app_manager_calendar_events` | GET | `/manager/campagne/{campagne}/calendar/events.json` | `ManagerCalendarController::events` |

---

## Coordinateur (Perimetre Delegue)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_coord_agents` | GET | `/coordinateur/campagne/{campagne}/agents` | `CoordinateurController::agents` |
| `app_coord_position` | GET, POST | `/coordinateur/campagne/{campagne}/positionner/{agent}` | `CoordinateurController::position` |
| `app_coord_modify` | GET, POST | `/coordinateur/campagne/{campagne}/modifier/{reservation}` | `CoordinateurController::modify` |
| `app_coord_cancel` | POST | `/coordinateur/campagne/{campagne}/annuler/{reservation}` | `CoordinateurController::cancel` |

---

## Documents

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_document_index` | GET | `/campagnes/{campagneId}/documents` | `DocumentController::index` |
| `app_document_upload` | GET, POST | `/campagnes/{campagneId}/documents/upload` | `DocumentController::upload` |
| `app_document_download` | GET | `/campagnes/{campagneId}/documents/{id}/download` | `DocumentController::download` |
| `app_document_delete` | POST | `/campagnes/{campagneId}/documents/{id}/delete` | `DocumentController::delete` |

---

## Templates Checklist

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_template_index` | GET | `/templates` | `ChecklistTemplateController::index` |
| `app_template_new` | GET, POST | `/templates/nouveau` | `ChecklistTemplateController::new` |
| `app_template_show` | GET | `/templates/{id}` | `ChecklistTemplateController::show` |
| `app_template_edit` | GET, POST | `/templates/{id}/modifier` | `ChecklistTemplateController::edit` |
| `app_template_toggle` | POST | `/templates/{id}/toggle` | `ChecklistTemplateController::toggle` |

---

## Habilitations

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_campagne_habilitations` | GET | `/campagnes/{id}/habilitations` | `HabilitationController::index` |
| `app_campagne_habilitation_add` | POST | `/campagnes/{id}/habilitations/ajouter` | `HabilitationController::add` |
| `app_campagne_habilitation_edit` | POST | `/campagnes/{id}/habilitations/{habilitationId}/modifier` | `HabilitationController::edit` |
| `app_campagne_habilitation_delete` | POST | `/campagnes/{id}/habilitations/{habilitationId}/supprimer` | `HabilitationController::delete` |

---

## Profil Utilisateur

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_profile` | GET | `/profil` | `ProfileController::index` |
| `app_profile_password` | GET, POST | `/profil/mot-de-passe` | `ProfileController::changePassword` |

---

## Recherche

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_search` | GET | `/recherche` | `SearchController::index` |
| `app_search_api` | GET | `/recherche/api` | `SearchController::api` |

---

## Partage (Lecture Seule)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_share_dashboard` | GET | `/share/{token}` | `ShareController::viewShared` |
| `app_share_generate` | POST | `/campagne/{id}/share/generate` | `ShareController::generateLink` |
| `app_share_revoke` | POST | `/campagne/{id}/share/revoke` | `ShareController::revokeLink` |
| `app_share_modal` | GET | `/campagne/{id}/share/modal` | `ShareController::shareModal` |

---

## Audit (Historique)

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `audit_index` | GET | `/audit` | `AuditController::index` |
| `audit_campagne` | GET | `/audit/campagne/{id}` | `AuditController::campagne` |
| `audit_operation` | GET | `/audit/operation/{id}` | `AuditController::operation` |

---

## Export Reservations

| Route | Methode | Path | Controller |
|-------|---------|------|------------|
| `app_reservation_export` | GET | `/campagnes/{campagne}/reservations/export.csv` | `ReservationExportController::export` |
