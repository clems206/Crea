<?php 
    session_start(); // Démarrer la session au cas où vous l'utiliseriez pour des messages, etc.
    require_once 'includes/db_config.php'; // Pour la connexion à la BDD
    $pdo = connect_db();

    // Récupérer les produits actifs pour la section "Produits Phares" (par exemple, les 3 plus récents)
    $produits_phares = [];
    if ($pdo) {
        try {
            // Sélectionner des produits qui ont une image et sont actifs
            $stmt_phares = $pdo->query("SELECT * FROM produits WHERE est_actif = 1 AND image_url IS NOT NULL AND image_url != '' ORDER BY date_creation DESC LIMIT 3");
            $produits_phares = $stmt_phares->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des produits phares: " . $e->getMessage());
        }
    }

    // Récupérer tous les produits actifs pour la page "Produits"
    $tous_les_produits = [];
    if ($pdo) {
        try {
            $stmt_tous_produits = $pdo->query("SELECT * FROM produits WHERE est_actif = 1 ORDER BY categorie ASC, nom ASC");
            $tous_les_produits = $stmt_tous_produits->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de tous les produits: " . $e->getMessage());
        }
    }

    // Inclure l'en-tête
    // Le titre de la page sera défini dynamiquement par Alpine.js ou par des pages spécifiques
    $page_title = "CréaMod3D - Accueil - Impression 3D Personnalisée"; // Titre par défaut pour index.php
    include 'includes/header.php'; 
?>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <section x-show="currentPage === 'accueil'" 
                 class="page-content active min-h-screen" 
                 x-init="document.title = 'CréaMod3D - Accueil - Impression 3D Personnalisée'">
            <div class="text-center py-16 bg-secondary rounded-lg shadow-lg">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold brand-text">Bienvenue chez <span class="brand-accent">CréaMod3D</span></h1>
                <p class="mt-6 text-lg sm:text-xl text-muted max-w-2xl mx-auto">Votre partenaire pour des créations 3D personnalisées uniques. De l'idée à l'objet, nous donnons vie à vos projets.</p>
                <div class="mt-10 flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <button @click="navigate('produits'); document.title = 'Nos Produits - CréaMod3D';" class="btn-primary text-lg font-semibold py-3 px-8 rounded-lg shadow-md hover:shadow-lg transition duration-300">Découvrir nos produits</button>
                    <button @click="navigate('devis'); document.title = 'Demande de Devis - CréaMod3D';" class="btn-secondary text-lg font-semibold py-3 px-8 rounded-lg shadow-md hover:shadow-lg transition duration-300">Demander un devis</button>
                </div>
            </div>

            <div class="py-16">
                <h2 class="text-3xl font-bold text-center mb-12 text-main">Nos Services</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8"> {/* Changé à 4 colonnes pour les services */}
                    <div class="bg-secondary p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                        <div class="text-brand-green text-3xl mb-4 text-center"><i class="fas fa-lightbulb"></i></div>
                        <h3 class="text-xl font-semibold mb-2 text-main text-center">Prénoms Lumineux</h3>
                        <p class="text-muted text-sm text-center">Des prénoms qui illuminent votre intérieur avec style et originalité.</p>
                    </div>
                    <div class="bg-secondary p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                        <div class="text-brand-green text-3xl mb-4 text-center"><i class="fas fa-copyright"></i></div>
                        <h3 class="text-xl font-semibold mb-2 text-main text-center">Logos Lumineux</h3>
                        <p class="text-muted text-sm text-center">Mettez votre marque en lumière avec un logo 3D éclairé sur mesure.</p>
                    </div>
                    <div class="bg-secondary p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                        <div class="text-brand-green text-3xl mb-4 text-center"><i class="fas fa-id-card-alt"></i></div> {/* Icône changée pour "modèles" */}
                        <h3 class="text-xl font-semibold mb-2 text-main text-center">Figurines & Modèles</h3>
                        <p class="text-muted text-sm text-center">Des figurines uniques, prototypes ou pièces spécifiques imprimées en résine ou filament.</p>
                    </div>
                     <div class="bg-secondary p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                        <div class="text-brand-green text-3xl mb-4 text-center"><i class="fas fa-paint-roller"></i></div>
                        <h3 class="text-xl font-semibold mb-2 text-main text-center">Peinture Aérographe</h3>
                        <p class="text-muted text-sm text-center">Finitions professionnelles à l'aérographe pour sublimer vos impressions 3D.</p>
                    </div>
                </div>
            </div>
            
            <div class="py-16">
                <h2 class="text-3xl font-bold text-center mb-12 text-main">Produits Phares</h2>
                <?php if (!empty($produits_phares)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php foreach ($produits_phares as $produit): ?>
                            <div class="bg-secondary rounded-lg shadow-lg overflow-hidden flex flex-col group">
                                <a href="produit_detail.php?id=<?php echo $produit['id']; ?>" class="block overflow-hidden">
                                    <?php 
                                    $image_url = (!empty($produit['image_url']) && file_exists($produit['image_url'])) 
                                                 ? htmlspecialchars($produit['image_url']) 
                                                 : 'https://placehold.co/600x400/A8C63F/2D2D2D?text=' . urlencode(htmlspecialchars($produit['nom']));
                                    ?>
                                    <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-105">
                                </a>
                                <div class="p-6 flex flex-col flex-grow">
                                    <h3 class="text-xl font-semibold mb-2 text-main"><a href="produit_detail.php?id=<?php echo $produit['id']; ?>" class="hover:text-brand-green"><?php echo htmlspecialchars($produit['nom']); ?></a></h3>
                                    <p class="text-muted mb-3 text-sm flex-grow">
                                        <?php echo nl2br(htmlspecialchars(substr($produit['description'], 0, 100))); echo strlen($produit['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                    <p class="text-2xl font-bold brand-accent mb-4">
                                        <?php echo number_format((float)$produit['prix_base'], 2, ',', ' '); ?> €
                                    </p>
                                    <a href="produit_detail.php?id=<?php echo $produit['id']; ?>" class="mt-auto w-full btn-primary text-center py-2.5 px-4 rounded-md font-semibold text-base">
                                        Voir les détails
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">Aucun produit phare à afficher pour le moment.</p>
                <?php endif; ?>
            </div>

            <div class="py-16 bg-secondary rounded-lg shadow-lg mt-12">
                <h2 class="text-3xl font-bold text-center mb-12 text-main">Témoignages Clients</h2>
                <div class="max-w-3xl mx-auto space-y-8 px-4">
                    <div class="p-6 border border-custom rounded-lg">
                        <p class="text-muted italic">"Service incroyable et produit final magnifique ! Je recommande vivement CréaMod3D."</p>
                        <p class="text-right font-semibold mt-2 text-main">- Client Satisfait A</p>
                    </div>
                    <div class="p-6 border border-custom rounded-lg">
                        <p class="text-muted italic">"Le prénom lumineux pour la chambre de ma fille est juste parfait. Merci !"</p>
                        <p class="text-right font-semibold mt-2 text-main">- Cliente Heureuse B</p>
                    </div>
                </div>
            </div>
        </section>

        <section x-show="currentPage === 'produits'" 
                 class="page-content min-h-screen"
                 x-init="document.title = 'Nos Produits - CréaMod3D'">
            <h1 class="text-3xl font-bold mb-4 text-main">Nos Produits</h1>
            <p class="text-muted mb-8">Découvrez notre gamme de créations 3D personnalisables.</p>
            
            <?php if (!empty($tous_les_produits)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($tous_les_produits as $produit): ?>
                        <div class="bg-secondary rounded-lg shadow-lg overflow-hidden group flex flex-col">
                             <a href="produit_detail.php?id=<?php echo $produit['id']; ?>" class="block overflow-hidden">
                                <?php 
                                $image_url_page_produit = (!empty($produit['image_url']) && file_exists($produit['image_url'])) 
                                             ? htmlspecialchars($produit['image_url']) 
                                             : 'https://placehold.co/600x400/A8C63F/2D2D2D?text=' . urlencode(htmlspecialchars($produit['nom']));
                                ?>
                                <img src="<?php echo $image_url_page_produit; ?>" alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="w-full h-56 object-cover transition-transform duration-300 group-hover:scale-105">
                            </a>
                            <div class="p-4 flex flex-col flex-grow">
                                <h3 class="text-lg font-semibold text-main mb-1"><a href="produit_detail.php?id=<?php echo $produit['id']; ?>" class="hover:text-brand-green"><?php echo htmlspecialchars($produit['nom']); ?></a></h3>
                                <?php if(!empty($produit['categorie'])): ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Catégorie : <?php echo htmlspecialchars($produit['categorie']); ?></p>
                                <?php endif; ?>
                                <p class="text-sm text-muted mb-2 flex-grow">
                                    <?php echo nl2br(htmlspecialchars(substr($produit['description'], 0, 70))); echo strlen($produit['description']) > 70 ? '...' : ''; ?>
                                </p>
                                <p class="text-xl font-bold brand-accent mb-3">
                                    <?php echo number_format((float)$produit['prix_base'], 2, ',', ' '); ?> €
                                </p>
                                <a href="produit_detail.php?id=<?php echo $produit['id']; ?>" class="mt-auto w-full btn-primary text-center py-2 px-3 rounded-md text-sm font-semibold">
                                    Voir les options
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted py-10">Aucun produit disponible pour le moment. Revenez bientôt !</p>
            <?php endif; ?>
            
            <p class="mt-8 text-center text-muted text-sm">La fonctionnalité complète de panier et de commande sera ajoutée ultérieurement.</p>
        </section>

        <section x-show="currentPage === 'devis'" 
                 class="page-content min-h-screen" 
                 x-data="quoteForm()" 
                 x-init="() => { 
                    document.title = 'Demande de Devis - CréaMod3D';
                    if (!Alpine.store('quoteFormStore')) Alpine.store('quoteFormStore', { submissionMessage: '', submissionStatus: '' }) 
                 }">
            <h1 class="text-3xl font-bold mb-4 text-main">Demande de Devis Détaillé</h1>
            <p class="text-muted mb-8">Remplissez ce formulaire pour obtenir une estimation pour votre projet personnalisé. Nous vous répondrons dans les plus brefs délais.</p>

            <form action="submit_devis.php" method="POST" enctype="multipart/form-data" class="space-y-6 bg-secondary p-6 sm:p-8 rounded-lg shadow-xl" @submit="clientSideValidationBeforeSubmit">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="nom" class="block text-sm font-medium text-main mb-1">Nom *</label>
                        <input type="text" name="nom" id="nom" x-model="formData.nom" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="Votre nom de famille">
                    </div>
                    <div>
                        <label for="prenom" class="block text-sm font-medium text-main mb-1">Prénom *</label>
                        <input type="text" name="prenom" id="prenom" x-model="formData.prenom" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="Votre prénom">
                    </div>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-main mb-1">Adresse Email *</label>
                    <input type="email" name="email" id="email" x-model="formData.email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$" class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="vous@exemple.com">
                </div>
                <div>
                    <label for="telephone" class="block text-sm font-medium text-main mb-1">Numéro de téléphone (Optionnel)</label>
                    <input type="tel" name="telephone" id="telephone" x-model="formData.telephone" pattern="[0-9\s\-\+]{10,15}" class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="0612345678">
                </div>

                <div>
                    <label for="type_projet" class="block text-sm font-medium text-main mb-1">Type de projet *</label>
                    <select id="type_projet" name="type_projet" x-model="formData.typeProjet" @change="calculatePrice" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm">
                        <option value="">Sélectionnez un type</option>
                        <option value="prenom_lumineux">Prénom Lumineux</option>
                        <option value="logo_lumineux">Logo Lumineux</option>
                        <option value="figurine">Figurine</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>

                <div x-show="formData.typeProjet === 'prenom_lumineux' || formData.typeProjet === 'logo_lumineux'" class="space-y-6 border-t border-custom pt-6" x-transition>
                    <div>
                        <label for="texte_personnalise" class="block text-sm font-medium text-main mb-1">Texte personnalisé (max 13 caractères, lettres et chiffres)</label>
                        <input type="text" name="texte_personnalise" id="texte_personnalise" x-model="formData.textePersonnalise" @input="validateText" maxlength="13" class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="Ex: SOPHIE">
                        <p class="text-xs text-muted mt-1" x-text="charCountMessage"></p>
                        <p x-show="textError" class="text-xs text-red-500 mt-1" x-text="textError"></p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="couleur_contour" class="block text-sm font-medium text-main mb-1">Couleur du contour</label>
                            <select id="couleur_contour" name="couleur_contour" x-model="formData.couleurContour" @change="calculatePrice" class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm">
                                <option value="noir">Noir (par défaut)</option>
                                <option value="rouge">Rouge</option>
                                <option value="bleu">Bleu</option>
                                <option value="vert">Vert</option>
                                <option value="rose">Rose</option>
                                <option value="violet">Violet</option>
                            </select>
                        </div>
                        <div>
                            <label for="eclairage" class="block text-sm font-medium text-main mb-1">Éclairage</label>
                            <select id="eclairage" name="eclairage" x-model="formData.eclairage" @change="calculatePrice" class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm">
                                <option value="blanc_chaud">Blanc Chaud (par défaut)</option>
                                <option value="blanc_froid">Blanc Froid</option>
                                <option value="rgb">RGB</option>
                            </select>
                        </div>
                    </div>
                    <div x-show="formData.eclairage === 'rgb'" x-transition>
                        <label for="option_rgb" class="block text-sm font-medium text-main mb-1">Option RGB</label>
                        <select id="option_rgb" name="option_rgb" x-model="formData.optionRgb" @change="calculatePrice" class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm">
                            <option value="aucune">Aucune</option>
                            <option value="bluetooth">Bluetooth (+5€)</option>
                            <option value="wifi">Wifi (+8€)</option>
                        </select>
                    </div>
                    <div class="mt-4 p-4 bg-primary border border-custom rounded-md" x-show="estimatedPrice > 0">
                        <p class="text-lg font-semibold text-main">Montant estimé avant devis : <span class="brand-accent" x-text="estimatedPrice.toFixed(2) + ' €'"></span></p>
                        <input type="hidden" name="montant_estime" :value="estimatedPrice.toFixed(2)">
                    </div>
                </div>
                
                <div>
                    <label for="description_projet" class="block text-sm font-medium text-main mb-1">Description de votre projet *</label>
                    <textarea id="description_projet" name="description_projet" rows="5" x-model="formData.descriptionProjet" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="Décrivez ici votre projet en détail..."></textarea>
                    <p class="text-xs text-muted mt-1">Plus vous donnez de détails, mieux nous pourrons estimer votre projet.</p>
                </div>

                <div x-show="formData.typeProjet === 'figurine' || formData.typeProjet === 'autre'">
                     <label for="file_upload" class="block text-sm font-medium text-main mb-1">Joindre un fichier (optionnel, max 5MB)</label>
                     <input type="file" id="file_upload" name="devis_fichier" @change="handleFileUpload" class="w-full text-sm text-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-green file:text-white hover:file:bg-green-700">
                     <p class="text-xs text-muted mt-1">Types de fichiers acceptés : STL, OBJ, JPG, PNG, PDF, ZIP, RAR.</p>
                     <p x-show="formData.fileName" class="text-sm text-muted mt-1">Fichier sélectionné : <span x-text="formData.fileName"></span></p>
                </div>

                <div class="pt-2">
                    <button type="submit" name="submit_devis" class="w-full btn-primary flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-green">
                        Envoyer la demande de devis
                    </button>
                </div>
            </form>

            <div x-show="Alpine.store('quoteFormStore').submissionMessage" x-cloak x-transition
                 :class="{ 'bg-green-100 border-green-500 text-green-700': Alpine.store('quoteFormStore').submissionStatus === 'success', 'bg-red-100 border-red-500 text-red-700': Alpine.store('quoteFormStore').submissionStatus === 'error' }"
                 class="mt-6 p-4 border rounded-md text-sm"
                 x-html="Alpine.store('quoteFormStore').submissionMessage"> {/* x-html pour interpréter les <br> et <ul> */}
            </div>
        </section>

        <section x-show="currentPage === 'contact'" 
                 class="page-content min-h-screen" 
                 x-data="contactForm()" 
                 x-init="() => { 
                    document.title = 'Contactez-Nous - CréaMod3D';
                    if (!Alpine.store('contactFormStore')) Alpine.store('contactFormStore', { contactSubmissionMessage: '', contactSubmissionStatus: '' }) 
                 }">
            <h1 class="text-3xl font-bold mb-8 text-main">Contactez-Nous</h1>
            <div class="grid md:grid-cols-2 gap-12">
                <form action="submit_contact.php" method="POST" class="space-y-6 bg-secondary p-6 sm:p-8 rounded-lg shadow-xl" @submit="clientSideContactValidation">
                    <div>
                        <label for="contact_nom" class="block text-sm font-medium text-main mb-1">Nom *</label>
                        <input type="text" name="contact_nom" id="contact_nom" x-model="contactData.nom" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="Votre nom">
                    </div>
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-main mb-1">Email *</label>
                        <input type="email" name="contact_email" id="contact_email" x-model="contactData.email" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="vous@exemple.com">
                    </div>
                    <div>
                        <label for="contact_sujet" class="block text-sm font-medium text-main mb-1">Sujet *</label>
                        <input type="text" name="contact_sujet" id="contact_sujet" x-model="contactData.sujet" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="Objet de votre message">
                    </div>
                    <div>
                        <label for="contact_message" class="block text-sm font-medium text-main mb-1">Message *</label>
                        <textarea name="contact_message" id="contact_message" rows="5" x-model="contactData.message" required class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm" placeholder="Votre message..."></textarea>
                    </div>
                    <div class="pt-2">
                         <button type="submit" name="submit_contact_form" class="w-full btn-primary flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-green">
                            Envoyer le message
                        </button>
                    </div>
                    <div x-show="Alpine.store('contactFormStore').contactSubmissionMessage" x-cloak x-transition
                        :class="{ 'bg-green-100 border-green-500 text-green-700': Alpine.store('contactFormStore').contactSubmissionStatus === 'success', 'bg-red-100 border-red-500 text-red-700': Alpine.store('contactFormStore').contactSubmissionStatus === 'error' }"
                        class="mt-4 p-3 border rounded-md text-sm"
                        x-html="Alpine.store('contactFormStore').contactSubmissionMessage"> {/* x-html pour interpréter les <br> et <ul> */}
                    </div>
                </form>

                <div class="space-y-6">
                    <div class="bg-secondary p-6 rounded-lg shadow-lg">
                        <h3 class="text-xl font-semibold mb-3 text-main">Nos Coordonnées</h3>
                        <p class="text-muted"><i class="fas fa-envelope mr-2 brand-accent"></i> <a href="mailto:contact@creamod3d.fr" class="hover:text-brand-green">contact@creamod3d.fr</a></p>
                    </div>
                    <div class="bg-secondary p-6 rounded-lg shadow-lg">
                        <h3 class="text-xl font-semibold mb-3 text-main">Suivez-nous</h3>
                        <div class="flex space-x-4">
                            <a href="https://www.facebook.com/profile.php?id=61576107364762" target="_blank" rel="noopener noreferrer" class="text-muted hover:text-brand-green text-2xl">
                                <i class="fab fa-facebook-square"></i>
                            </a>
                            <a href="https://www.instagram.com/creamod3d.fr/" target="_blank" rel="noopener noreferrer" class="text-muted hover:text-brand-green text-2xl">
                                <i class="fab fa-instagram-square"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <section x-show="currentPage === 'admin-dashboard'" 
                 class="page-content min-h-screen"
                 x-init="document.title = 'Administration - CréaMod3D'">
            <h1 class="text-3xl font-bold mb-8 text-main">Tableau de Bord Administrateur</h1>
            <div class="bg-secondary p-6 rounded-lg shadow-xl">
                <p class="text-muted">Cette section est réservée à l'administration du site.</p>
                 <div class="mt-6">
                    <a href="admin_login.php" class="btn-secondary py-2 px-4 rounded-md">Se connecter</a>
                </div>
            </div>
        </section>

    </main>

<?php 
    // Inclure le pied de page
    include 'includes/footer.php'; 
?>
