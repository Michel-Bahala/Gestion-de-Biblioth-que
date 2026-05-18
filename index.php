<?php
// ==========================================
// 1. CONFIGURATION ET INITIALISATION PRO
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mbr_host = '127.0.0.1'; 
$mbr_db   = 'bibliotheque';
$mbr_user = 'root';
$mbr_pass = ''; 
try {
    $mbr_pdo = new PDO("mysql:host=$mbr_host;charset=utf8", $mbr_user, $mbr_pass);
    $mbr_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $mbr_pdo->exec("CREATE DATABASE IF NOT EXISTS `$mbr_db` CHARACTER SET utf8 COLLATE utf8_general_ci;");
    $mbr_pdo->exec("USE `$mbr_db`;");
    
    // Table Livres
    $mbr_pdo->exec("CREATE TABLE IF NOT EXISTS livres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255) NOT NULL,
        auteur VARCHAR(255) NOT NULL,
        annee INT NOT NULL,
        disponible BOOLEAN DEFAULT TRUE
    );");

    // Table Emprunts
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
// 2. FONCTIONS LOGIQUE MÉTIER
// ==========================================

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

function enregistrerEmprunt($mbr_pdo, $mbr_livre_id, $mbr_emprunteur, $mbr_telephone, $mbr_jours) {
    $mbr_date_emprunt = date('Y-m-d');
    $mbr_date_retour_prevue = date('Y-m-d', strtotime("+$mbr_jours days"));
    
    $mbr_sql1 = "INSERT INTO emprunts (livre_id, emprunteur, telephone, date_emprunt, date_retour_prevue) VALUES (?, ?, ?, ?, ?)";
    $mbr_pdo->prepare($mbr_sql1)->execute([$mbr_livre_id, $mbr_emprunteur, $mbr_telephone, $mbr_date_emprunt, $mbr_date_retour_prevue]);
    
    $mbr_sql2 = "UPDATE livres SET disponible = FALSE WHERE id = ?";
    return $mbr_pdo->prepare($mbr_sql2)->execute([$mbr_livre_id]);
}

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

// Validation basique côté serveur
function validerLivre($titre, $auteur, $annee) {
    $errors = [];
    if (empty($titre)) $errors[] = "Le titre est obligatoire.";
    if (empty($auteur)) $errors[] = "L'auteur est obligatoire.";
    if (empty($annee) || !is_numeric($annee) || (int)$annee < 1000 || (int)$annee > (int)date('Y')) {
        $errors[] = "L'année doit être un nombre valide entre 1000 et " . date('Y') . ".";
    }
    return $errors;
}

// ==========================================
// 3. INTERCEPTION DES ACTIONS (CONTROLLER)
// ==========================================
$errors = [];
$script_name = basename(__FILE__);

// Action : Ajouter ou Modifier un livre
if (isset($_POST['enregistrer_livre'])) {
    $titre = trim($_POST['titre'] ?? '');
    $auteur = trim($_POST['auteur'] ?? '');
    $annee = trim($_POST['annee'] ?? '');
    $id = $_POST['id'] ?? '';

    $errors = validerLivre($titre, $auteur, $annee);

    if (empty($errors)) {
        if (!empty($id) && is_numeric($id)) {
            modifierLivre($mbr_pdo, (int)$id, $titre, $auteur, (int)$annee);
        } else {
            ajouterLivre($mbr_pdo, $titre, $auteur, (int)$annee);
        }
        header("Location: $script_name");
        exit;
    }
}

// Action : Valider un emprunt
if (isset($_POST['valider_emprunt'])) {
    if (!empty($_POST['livre_id']) && !empty($_POST['emprunteur']) && !empty($_POST['telephone'])) {
        enregistrerEmprunt($mbr_pdo, (int)$_POST['livre_id'], $_POST['emprunteur'], $_POST['telephone'], (int)$_POST['duree']);
        header("Location: $script_name");
        exit;
    }
}

// Action GET : Retour, Suppression, Modification
$mbr_livre_a_modifier = null;
$mbr_livre_a_emprunter = null;

if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    switch ($_GET['action']) {
        case 'rendre':
            enregistrerRetour($mbr_pdo, $id);
            header("Location: $script_name");
            exit;
        case 'supprimer':
            supprimerLivre($mbr_pdo, $id);
            header("Location: $script_name");
            exit;
        case 'modifier':
            $mbr_stmt = $mbr_pdo->prepare("SELECT * FROM livres WHERE id = ?");
            $mbr_stmt->execute([$id]);
            $mbr_livre_a_modifier = $mbr_stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'emprunter':
            $mbr_stmt = $mbr_pdo->prepare("SELECT * FROM livres WHERE id = ?");
            $mbr_stmt->execute([$id]);
            $mbr_livre_a_emprunter = $mbr_stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }
}

$mbr_search = $_GET['search'] ?? '';
$mbr_livres = listerLivresComplet($mbr_pdo, $mbr_search);

// Statistiques du Dashboard
$mbr_total_livres = count($mbr_livres);
$mbr_dispo_livres = count(array_filter($mbr_livres, function($l) { return $l['disponible']; }));
$mbr_emprunts_en_cours = $mbr_total_livres - $mbr_dispo_livres;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bibliothèque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0b0f19, #111827); color: #f3f4f6; font-family: 'Segoe UI', sans-serif; }
        .sidebar { background: rgba(15, 23, 42, 0.95); min-height: 100vh; padding: 25px; border-right: 1px solid rgba(255,255,255,0.05); }
        .main-content { padding: 40px; }
        .card-pro { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 15px; padding: 25px; transition: 0.3s; mb-4; }
        .card-pro:hover { transform: translateY(-2px); }
        .form-control { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; }
        .form-control:focus { border-color: #6366f1; box-shadow: 0 0 10px rgba(99,102,241,0.3); background: rgba(255,255,255,0.07); color: white; }
        .btn-pro { background: linear-gradient(135deg, #6366f1, #4f46e5); border: none; font-weight: bold; color: white; }
        .btn-pro:hover { background: linear-gradient(135deg, #4f46e5, #4338ca); color: white; }
        .table { border-collapse: separate; border-spacing: 0 10px; }
        .table tbody tr { background: rgba(255,255,255,0.03); transition: 0.3s; }
        .table tbody tr:hover { background: rgba(99,102,241,0.08); }
        .status-ok { color: #10b981; font-weight: 600; }
        .status-bad { color: #f59e0b; font-weight: 600; }
        @media(max-width:768px){ .sidebar { min-height: auto; width: 100%; } }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <div class="col-md-3 sidebar">
            <h4 class="mb-4">📚 BiblioTech</h4>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$mbr_livre_a_emprunter): ?>
                <div class="card-pro">
                    <h6 class="mb-3 text-indigo"><?= $mbr_livre_a_modifier ? '✏️ Modifier le livre' : '➕ Ajouter un livre' ?></h6>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($mbr_livre_a_modifier['id'] ?? '') ?>">
                        
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Titre</label>
                            <input name="titre" class="form-control form-control-sm" value="<?= htmlspecialchars($mbr_livre_a_modifier['titre'] ?? '') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Auteur</label>
                            <input name="auteur" class="form-control form-control-sm" value="<?= htmlspecialchars($mbr_livre_a_modifier['auteur'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Année</label>
                            <input type="number" name="annee" class="form-control form-control-sm" value="<?= htmlspecialchars($mbr_livre_a_modifier['annee'] ?? '') ?>" required>
                        </div>

                        <button type="submit" name="enregistrer_livre" class="btn btn-pro btn-sm w-100">
                            <?= $mbr_livre_a_modifier ? 'Sauvegarder' : 'Ajouter' ?>
                        </button>
                        
                        <?php if ($mbr_livre_a_modifier): ?>
                            <a href="<?= $script_name ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2">Annuler</a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($mbr_livre_a_emprunter): ?>
                <div class="card-pro border-warning">
                    <h6 class="mb-3 text-warning">🤝 Emprunter un livre</h6>
                    <p class="small text-muted mb-2">Livre : <strong><?= htmlspecialchars($mbr_livre_a_emprunter['titre']) ?></strong></p>
                    
                    <form method="POST">
                        <input type="hidden" name="livre_id" value="<?= (int)$mbr_livre_a_emprunter['id'] ?>">
                        
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Nom de l'emprunteur</label>
                            <input name="emprunteur" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Téléphone</label>
                            <input name="telephone" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Durée (jours)</label>
                            <select name="duree" class="form-control form-control-sm">
                                <option value="7">7 jours</option>
                                <option value="14" selected>14 jours</option>
                                <option value="30">30 jours</option>
                            </select>
                        </div>

                        <button type="submit" name="valider_emprunt" class="btn btn-warning btn-sm w-100 text-dark fw-bold">
                            Confirmer l'emprunt
                        </button>
                        <a href="<?= $script_name ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2">Annuler</a>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-9 main-content">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold m-0">📊 Dashboard</h3>
                    <small class="text-muted">Gestion intelligente de la bibliothèque</small>
                </div>
                <div class="badge bg-dark p-2 fs-6 border border-secondary">
                    📅 <?= date('d M Y') ?>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card-pro text-center">
                        <span class="text-muted small uppercase">Total Livres</span>
                        <h2 class="fw-bold text-white m-0 mt-1"><?= $mbr_total_livres ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-pro text-center">
                        <span class="text-muted small uppercase">Disponibles</span>
                        <h2 class="fw-bold text-success m-0 mt-1"><?= $mbr_dispo_livres ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-pro text-center">
                        <span class="text-muted small uppercase">Emprunts en cours</span>
                        <h2 class="fw-bold text-warning m-0 mt-1"><?= $mbr_emprunts_en_cours ?></h2>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="m-0">📚 Collection des livres</h5>
                <form class="d-flex" method="GET">
                    <input class="form-control form-control-sm me-2" name="search" type="search" placeholder="Titre ou auteur..." value="<?= htmlspecialchars($mbr_search) ?>">
                    <button class="btn btn-sm btn-outline-light" type="submit">Rechercher</button>
                </form>
            </div>

            <div class="card-pro">
                <div class="table-responsive">
                    <table class="table text-white align-middle m-0">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Année</th>
                                <th>Statut / Infos Emprunt</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mbr_livres)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Aucun livre trouvé dans la base de données.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php foreach($mbr_livres as $l): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($l['titre']) ?></td>
                                <td><?= htmlspecialchars($l['auteur']) ?></td>
                                <td><?= htmlspecialchars($l['annee']) ?></td>
                                <td>
                                    <?php if($l['disponible']): ?>
                                        <span class="status-ok">🟢 Disponible</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="?action=emprunter&id=<?= $l['id'] ?>" class="btn btn-success btn-sm me-1">🤝 Emprunter</a>
                                    <?php else: ?>
                                        <span class="status-bad">🟠 Emprunté par :</span> 
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($l['emprunteur']) ?> (Tél: <?= htmlspecialchars($l['telephone']) ?>)<br>
                                            <span class="text-danger">Retour prévu : <?= date('d/m/Y', strtotime($l['date_retour_prevue'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="?action=rendre&id=<?= $l['id'] ?>" class="btn btn-info btn-sm me-1 text-dark fw-bold">↩️ Retour</a>
                                    <?php endif; ?>

                                    <a href="?action=modifier&id=<?= $l['id'] ?>" class="btn btn-outline-light btn-sm me-1">✏️</a>
                                    <a href="?action=supprimer&id=<?= $l['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer définitivement ce livre ?');">🗑️</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
