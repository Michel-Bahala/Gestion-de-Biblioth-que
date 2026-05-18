<?php
// ==========================================
// CONFIGURATION ET INITIALISATION PRO
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mbr_host = '127.0.0.1'; 
$mbr_db   = 'bibliotheque';
$mbr_user = 'root';
$mbr_pass = '12345'; 

try {
    $mbr_pdo = new PDO("mysql:host=$mbr_host;charset=utf8", $mbr_user, $mbr_pass);
    $mbr_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $mbr_pdo->exec("CREATE DATABASE IF NOT EXISTS `$mbr_db` CHARACTER SET utf8 COLLATE utf8_general_ci;");
    $mbr_pdo->exec("USE `$mbr_db`;");
    
    // Table Livres mise à jour
    $mbr_pdo->exec("CREATE TABLE IF NOT EXISTS livres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255) NOT NULL,
        auteur VARCHAR(255) NOT NULL,
        annee INT NOT NULL,
        disponible BOOLEAN DEFAULT TRUE
    );");

    // NOUVELLE TABLE : Gestion des emprunteurs et dates
    $mbr_pdo->exec("CREATE TABLE IF NOT EXISTS emprunts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        livre_id INT NOT NULL,
        emprunteur VARCHAR(255) NOT NULL,
        telephone VARCHAR(50) NOT NULL,
        date_emprunt DATE NOT NULL,
        date_retour_prevue DATE NOT NULL,
        statut VARCHAR(20) DEFAULT 'EN_COURS',
        FOREIGN KEY (livre_id) REFERENCES livres(id) ON DELETE CASCADE
    );");
} catch (PDOException $mbr_e) {
    die("Erreur critique d'initialisation : " . $mbr_e->getMessage());
}


// ==========================================
// FONCTIONS LOGIQUE MÉTIER (CRUD AVANCÉ)
// ==========================================

function compterLivres($mbr_pdo) {
    $mbr_stmt = $mbr_pdo->query("SELECT COUNT(*) FROM livres");
    return (int)$mbr_stmt->fetchColumn();
}

function ajouterLivre($mbr_pdo, $mbr_titre, $mbr_auteur, $mbr_annee) {
    $mbr_sql = "INSERT INTO livres (titre, auteur, annee) VALUES (?, ?, ?)";
    return $mbr_pdo->prepare($mbr_sql)->execute([$mbr_titre, $mbr_auteur, $mbr_annee]);
}

function modifierLivre($mbr_pdo, $mbr_id, $mbr_titre, $mbr_auteur, $mbr_annee) {
    $mbr_sql = "UPDATE livres SET titre = ?, auteur = ?, annee = ? WHERE id = ?";
    return $mbr_pdo->prepare($mbr_sql)->execute([$mbr_titre, $mbr_auteur, $mbr_annee, $mbr_id]);
}

function supprimerLivre($mbr_pdo, $mbr_id) {
    $mbr_sql = "DELETE FROM livres WHERE id = ?";
    return $mbr_pdo->prepare($mbr_sql)->execute([$mbr_id]);
}

// Enregistrer un nouvel emprunt (Sortie de livre)
function enregistrerEmprunt($mbr_pdo, $mbr_livre_id, $mbr_emprunteur, $mbr_telephone, $mbr_jours) {
    $mbr_date_emprunt = date('Y-m-d');
    $mbr_date_retour_prevue = date('Y-m-d', strtotime("+$mbr_jours days"));
    
    $mbr_sql1 = "INSERT INTO emprunts (livre_id, emprunteur, telephone, date_emprunt, date_retour_prevue) VALUES (?, ?, ?, ?, ?)";
    $mbr_pdo->prepare($mbr_sql1)->execute([$mbr_livre_id, $mbr_emprunteur, $mbr_telephone, $mbr_date_emprunt, $mbr_date_retour_prevue]);
    
    $mbr_sql2 = "UPDATE livres SET disponible = FALSE WHERE id = ?";
    return $mbr_pdo->prepare($mbr_sql2)->execute([$mbr_livre_id]);
}

// Enregistrer un retour de livre
function enregistrerRetour($mbr_pdo, $mbr_livre_id) {
    $mbr_sql1 = "UPDATE emprunts SET statut = 'RENDU' WHERE livre_id = ? AND statut = 'EN_COURS'";
    $mbr_pdo->prepare($mbr_sql1)->execute([$mbr_livre_id]);
    
    $mbr_sql2 = "UPDATE livres SET disponible = TRUE WHERE id = ?";
    return $mbr_pdo->prepare($mbr_sql2)->execute([$mbr_livre_id]);
}

function listerLivresComplet($mbr_pdo, $mbr_search = '') {
    if (!empty($mbr_search)) {
        $mbr_sql = "SELECT l.*, e.emprunteur, e.date_retour_prevue, e.telephone 
                FROM livres l 
                LEFT JOIN emprunts e ON l.id = e.livre_id AND e.statut = 'EN_COURS'
                WHERE l.titre LIKE ? OR l.auteur LIKE ?
                ORDER BY l.id DESC";
        $mbr_stmt = $mbr_pdo->prepare($mbr_sql);
        $mbr_stmt->execute(["%$mbr_search%", "%$mbr_search%"]);
        return $mbr_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $mbr_sql = "SELECT l.*, e.emprunteur, e.date_retour_prevue, e.telephone 
                FROM livres l 
                LEFT JOIN emprunts e ON l.id = e.livre_id AND e.statut = 'EN_COURS'
                ORDER BY l.id DESC";
        return $mbr_pdo->query($mbr_sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ==========================================
// INTERCEPTION DES ACTIONS CONTROLLER
// ==========================================

if (isset($_POST['enregistrer_livre'])) {
    if (!empty($_POST['titre']) && !empty($_POST['auteur']) && is_numeric($_POST['annee'])) {
        if (!empty($_POST['id'])) {
            modifierLivre($mbr_pdo, (int)$_POST['id'], $_POST['titre'], $_POST['auteur'], $_POST['annee']);
        } else {
            ajouterLivre($mbr_pdo, $_POST['titre'], $_POST['auteur'], $_POST['annee']);
        }
        header('Location: tpphp.php');
        exit;
    }
}

if (isset($_POST['valider_emprunt'])) {
    if (!empty($_POST['livre_id']) && !empty($_POST['emprunteur']) && !empty($_POST['telephone'])) {
        enregistrerEmprunt($mbr_pdo, (int)$_POST['livre_id'], $_POST['emprunteur'], $_POST['telephone'], (int)$_POST['duree']);
        header('Location: tpphp.php');
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'rendre' && isset($_GET['id'])) {
    enregistrerRetour($mbr_pdo, (int)$_GET['id']);
    header('Location: tpphp.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    supprimerLivre($mbr_pdo, (int)$_GET['id']);
    header('Location: tpphp.php');
    exit;
}

$mbr_livre_a_modifier = null;
if (isset($_GET['action']) && $_GET['action'] === 'modifier' && isset($_GET['id'])) {
    $mbr_stmt = $mbr_pdo->prepare("SELECT * FROM livres WHERE id = ?");
    $mbr_stmt->execute([(int)$_GET['id']]);
    $mbr_livre_a_modifier = $mbr_stmt->fetch(PDO::FETCH_ASSOC);
}

$mbr_search = $_GET['search'] ?? '';
$mbr_livres = listerLivresComplet($mbr_pdo, $mbr_search);

// Stats
$mbr_total_livres = count($mbr_livres);
$mbr_dispo_livres = count(array_filter($mbr_livres, function($mbr_l) { return $mbr_l['disponible']; }));
$mbr_emprunts_en_cours = $mbr_total_livres - $mbr_dispo_livres;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pro Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-sidebar: #111827;
            --bg-card: #1f2937;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --border: rgba(255, 255, 255, 0.08);
            --text-gray: #9ca3af;
        }

        body {
            background-color: var(--bg-main);
            color: #f3f4f6;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
        }

        .sidebar {
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            min-height: 100vh;
            position: fixed;
            width: 340px;
            padding: 30px 24px;
        }

        .main-content {
            margin-left: 340px;
            padding: 40px 45px;
        }

        .f-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .f-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-gray);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .f-input {
            background-color: rgba(11, 15, 25, 0.6) !important;
            border: 1px solid var(--border) !important;
            color: #ffffff !important;
            border-radius: 10px;
            padding: 11px 14px;
        }

        .f-input:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25) !important;
        }

        .btn-pro {
            background-color: var(--accent);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-pro:hover { background-color: var(--accent-hover); color: white; }

        /* Modern Custom Table Design */
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
        }

        .custom-table th {
            color: var(--text-gray);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            padding: 0 20px 10px 20px;
        }

        .custom-table tbody tr {
            background-color: var(--bg-sidebar);
            border: 1px solid var(--border);
        }

        .custom-table td {
            padding: 18px 20px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .custom-table td:first-child {
            border-left: 1px solid var(--border);
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .custom-table td:last-child {
            border-right: 1px solid var(--border);
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .badge-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }

        .status-available { background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-borrowed { background: rgba(245, 158, 11, 0.12); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-overdue { background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        .btn-action-round {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            color: #ffffff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-action-round:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .btn-action-delete:hover { border-color: #ef4444; color: #ef4444; }
        .btn-action-success:hover { border-color: #10b981; color: #10b981; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="d-flex align-items-center mb-4">
        <div class="p-2 bg-primary rounded-3 me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
            <i class="bi bi-building-fill-gear text-white fs-5"></i>
        </div>
        <div>
            <h6 class="m-0 fw-bold">BiblioTech Pro</h6>
            <span class="text-muted" style="font-size: 0.75rem;">Système de Gestion</span>
        </div>
    </div>
    <hr style="border-color: var(--border);">

    <div class="mb-4 mt-4">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-journal-plus me-1"></i> Catalogage Index</h6>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $mbr_livre_a_modifier['id'] ?? '' ?>">
            <div class="mb-3">
                <label class="f-label">Titre du livre *</label>
                <input type="text" name="titre" class="form-control f-input" placeholder="Saisir le titre complet" value="<?= htmlspecialchars($mbr_livre_a_modifier['titre'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="f-label">Nom de l'auteur *</label>
                <input type="text" name="auteur" class="form-control f-input" placeholder="Nom de l'auteur" value="<?= htmlspecialchars($mbr_livre_a_modifier['auteur'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <label class="f-label">Année de publication *</label>
                <input type="number" name="annee" class="form-control f-input" placeholder="Ex: 2024" value="<?= htmlspecialchars($mbr_livre_a_modifier['annee'] ?? '') ?>" required>
            </div>
            <button type="submit" name="enregistrer_livre" class="btn btn-pro w-100">
                <?= $mbr_livre_a_modifier ? 'Enregistrer les modifications' : 'Ajouter au catalogue' ?>
            </button>
            <?php if ($mbr_livre_a_modifier): ?>
                <a href="tpphp.php" class="btn btn-sm btn-outline-secondary w-100 mt-2 py-2" style="border-radius:10px;">Annuler l'édition</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold m-0">Dashboard Général</h4>
            <p class="text-muted m-0" style="font-size: 0.8rem;">Suivi en temps réel des ouvrages et mouvements de fiches.</p>
        </div>
        <div class="text-muted fw-medium" style="font-size: 0.85rem;"><i class="bi bi-calendar3 me-1"></i> <?= date('d M Y') ?></div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="f-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="f-label">Ouvrages Enregistrés</div>
                    <h3 class="m-0 fw-bold"><?= $mbr_total_livres ?></h3>
                </div>
                <div class="fs-2 text-primary bg-dark p-2 rounded-3 px-3"><i class="bi bi-book"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="f-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="f-label">Disponibles en Rayon</div>
                    <h3 class="m-0 fw-bold text-success"><?= $mbr_dispo_livres ?></h3>
                </div>
                <div class="fs-2 text-success bg-dark p-2 rounded-3 px-3"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="f-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="f-label">Emprunts en Cours</div>
                    <h3 class="m-0 fw-bold text-warning"><?= $mbr_emprunts_en_cours ?></h3>
                </div>
                <div class="fs-2 text-warning bg-dark p-2 rounded-3 px-3"><i class="bi bi-arrow-left-right"></i></div>
            </div>
        </div>
    </div>

    <div class="f-card py-3 mb-4">
        <form method="GET" class="row g-2">
            <div class="col-md-10">
                <div class="position-relative">
                    <i class="bi bi-search position-absolute text-muted" style="top: 13px; left: 15px;"></i>
                    <input type="text" name="search" class="form-control f-input w-100" style="padding-left: 45px;" placeholder="Recherche rapide par Titre, Thème ou Auteur..." value="<?= htmlspecialchars($mbr_search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-pro w-100 py-2" type="submit">Rechercher</button>
            </div>
        </form>
    </div>

    <h6 class="fw-bold text-white mb-2"><i class="bi bi-list-stars me-1"></i> Répertoire Principal des Volumes</h6>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Désignation du Volume</th>
                    <th>Année</th>
                    <th>Statut Courant</th>
                    <th>Détails Emprunteur</th>
                    <th class="text-end">Actions de Registre</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($mbr_livres) > 0): ?>
                    <?php foreach ($mbr_livres as $mbr_livre): ?>
                    <?php 
                        // Calcul du retard potentiel
                        $mbr_is_overdue = false;
                        $mbr_date_br_info = '';
                        if (!$mbr_livre['disponible'] && !empty($mbr_livre['date_retour_prevue'])) {
                            $mbr_is_overdue = (strtotime($mbr_livre['date_retour_prevue']) < strtotime(date('Y-m-d')));
                            $mbr_date_br_info = date('d/m/Y', strtotime($mbr_livre['date_retour_prevue']));
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-white fs-6"><?= htmlspecialchars($mbr_livre['titre']) ?></div>
                            <span class="text-muted" style="font-size: 0.8rem;">par <?= htmlspecialchars($mbr_livre['auteur']) ?></span>
                        </td>
                        <td><span class="text-secondary fw-semibold"><?= (int)$mbr_livre['annee'] ?></span></td>
                        <td>
                            <?php if ($mbr_livre['disponible']): ?>
                                <span class="badge-status status-available"><i class="bi bi-dot fs-5"></i> En Rayon</span>
                            <?php elseif ($mbr_is_overdue): ?>
                                <span class="badge-status status-overdue"><i class="bi bi-exclamation-triangle-fill me-1" style="font-size: 10px;"></i> En Retard</span>
                            <?php else: ?>
                                <span class="badge-status status-borrowed"><i class="bi bi-clock-history me-1" style="font-size: 10px;"></i> Emprunté</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$mbr_livre['disponible']): ?>
                                <div class="fw-semibold text-white" style="font-size: 0.85rem;"><i class="bi bi-person me-1 text-muted"></i> <?= htmlspecialchars($mbr_livre['emprunteur']) ?></div>
                                <span class="text-muted d-block" style="font-size: 0.75rem;"><i class="bi bi-telephone me-1"></i> Tel: <?= htmlspecialchars($mbr_livre['telephone']) ?></span>
                                <span class="text-info d-block" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-1"></i> Retour prévu : <?= $mbr_date_br_info ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($mbr_livre['disponible']): ?>
                                <button class="btn-action-round btn-action-success" title="Attribuer l'Emprunt" data-bs-toggle="modal" data-bs-target="#empruntModal<?= $mbr_livre['id'] ?>">
                                    <i class="bi bi-person-plus-fill"></i>
                                </button>
                            <?php else: ?>
                                <a href="?action=rendre&id=<?= $mbr_livre['id'] ?>" class="btn-action-round text-info" title="Marquer comme Rendu" onclick="return confirm('Confirmer le retour de ce livre en rayon ?');">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="?action=modifier&id=<?= $mbr_livre['id'] ?>" class="btn-action-round text-warning" title="Éditer la fiche"><i class="bi bi-pencil-square"></i></a>
                            <a href="?action=supprimer&id=<?= $mbr_livre['id'] ?>" class="btn-action-round btn-action-delete" title="Supprimer du catalogue" onclick="return confirm('Supprimer définitivement ce livre ?');"><i class="bi bi-trash3"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="empruntModal<?= $mbr_livre['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0" style="background-color: var(--bg-card); color: white; border-radius:14px;">
                                <div class="modal-header border-bottom-0">
                                    <h6 class="modal-title fw-bold"><i class="bi bi-person-vcard text-primary me-2"></i> Formulaire de Sortie d'Ouvrage</h6>
                                    <button type="button" class="btn-close btn-close-white" data-bs-shadow="none" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
<p class="text-muted style" style="font-size:0.8rem;">Livre sélectionné : <strong class="text-white"><?= htmlspecialchars($mbr_livre['titre']) ?></strong></p>
                                <input type="hidden" name="livre_id" value="<?= $mbr_livre['id'] ?>">
                                        
                                        <div class="mb-3">
                                            <label class="f-label">Nom de l'Emprunteur *</label>
                                            <input type="text" name="emprunteur" class="form-control f-input" placeholder="Saisir son Nom et Prénom" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="f-label">Numéro de Téléphone *</label>
                                            <input type="text" name="telephone" class="form-control f-input" placeholder="Ex: +243 999..." required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="f-label">Durée de l'accord (Jours) *</label>
                                            <select name="duree" class="form-select f-input text-white">
                                                <option value="7">1 Semaine (7 jours)</option>
                                                <option value="14" selected>2 Semaines (14 jours)</option>
                                                <option value="30">1 Mois (30 jours)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-top-0 d-grid">
                                        <button type="submit" name="valider_emprunt" class="btn btn-pro w-100">Valider et Sortir le Volume</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-archive fs-3 d-block mb-2 text-secondary"></i>
                            Aucun livre n'est présent ou ne correspond à vos filtres.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






