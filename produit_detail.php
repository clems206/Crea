<?php
session_start(); // Démarrer la session au cas où vous l'utiliseriez plus tard (ex: pour le panier)
require_once 'includes/db_config.php'; // Inclure la configuration de la BDD
$pdo = connect_db(); // Établir la connexion

$product = null;
$product_id = null;
$page_title_product = "Détail du Produit"; // Titre par défaut
$product_options_grouped = []; // Pour stocker les options groupées par nom_groupe_option
$json_options_for_alpine = "[]"; // JSON vide par défaut pour Alpine

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $product_id = (int)$_GET['id'];

    if ($pdo) {
        try {
            // Récupérer le produit principal
            $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = :id AND est_actif = 1");
            $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $page_title_product = htmlspecialchars($product['nom']) . " - CréaMod3D";

                // Récupérer les options du produit, groupées et prêtes pour Alpine.js
                $stmt_options = $pdo->prepare("
                    SELECT nom_groupe_option, type_champ, texte_aide,
                           GROUP_CONCAT(CONCAT_WS('::', id, valeur_option, supplement_prix) ORDER BY id SEPARATOR '||') as valeurs_concat
                    FROM produit_options 
                    WHERE produit_id = :produit_id 
                    GROUP BY nom_groupe_option, type_champ, texte_aide
                    ORDER BY MIN(ordre_affichage) ASC, nom_groupe_option ASC
                ");
                $stmt_options->bindParam(':produit_id', $product_id, PDO::PARAM_INT);
                $stmt_options->execute();
                $options_raw_grouped = $stmt_options->fetchAll(PDO::FETCH_ASSOC);

                $temp_alpine_options = [];
                foreach ($options_raw_grouped as $group) {
                    $valeurs_array = [];
                    if (!empty($group['valeurs_concat'])) {
                        $valeurs_split = explode('||', $group['valeurs_concat']);
                        foreach ($valeurs_split as $val_str) {
                            list($opt_id, $opt_val, $opt_supp) = explode('::', $val_str, 3);
                            $valeurs_array[] = [
                                'id' => (int)$opt_id,
                                'valeur' => $opt_val,
                                'supplement' => (float)$opt_supp
                            ];
                        }
                    }
                    $temp_alpine_options[] = [
                        'nomGroupe' => $group['nom_groupe_option'],
                        'type' => $group['type_champ'],
                        'aide' => $group['texte_aide'],
                        'valeurs' => $valeurs_array
                    ];
                }
                $product_options_grouped = $temp_alpine_options; // Pour l'affichage PHP direct
                $json_options_for_alpine = json_encode($temp_alpine_options);

            } else {
                // Produit non trouvé ou inactif
                $product_id = null; 
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du produit ID $product_id: " . $e->getMessage());
            $product_id = null; 
        }
    } else {
        // Erreur de connexion BDD
        $product_id = null;
    }
} else {
    // ID non fourni ou invalide
    $product_id = null;
}

// Définir le titre de la page pour le header
$page_title = $page_title_product; 
include 'includes/header.php'; // Inclure l'en-tête
?>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 min-h-[calc(100vh-280px)]" 
      x-data="productOptionsHandler({ 
          basePrice: <?php echo $product ? (float)$product['prix_base'] : 0; ?>, 
          optionsConfig: <?php echo $json_options_for_alpine; ?> 
      })"
      x-init="initAlpine()">

    <?php if ($product): ?>
        <div class="bg-secondary shadow-xl rounded-lg p-4 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-12">
                <div>
                    <?php
                    $image_url = (!empty($product['image_url']) && file_exists($product['image_url']))
                                 ? htmlspecialchars($product['image_url'])
                                 : 'https://placehold.co/600x600/A8C63F/2D2D2D?text=' . urlencode(htmlspecialchars($product['nom']));
                    ?>
                    <img src="<?php echo $image_url; ?>?t=<?php echo time(); // Cache busting ?>" alt="<?php echo htmlspecialchars($product['nom']); ?>" class="w-full h-auto max-h-[400px] md:max-h-[500px] object-contain rounded-lg shadow-md border border-custom mb-4">
                    
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mt-4">
                        <img src="https://placehold.co/150x150/eeeeee/cccccc?text=Vue+2" alt="Vue supplémentaire 1" class="w-full h-auto object-cover rounded-md border border-custom cursor-pointer hover:opacity-75">
                        <img src="https://placehold.co/150x150/eeeeee/cccccc?text=Vue+3" alt="Vue supplémentaire 2" class="w-full h-auto object-cover rounded-md border border-custom cursor-pointer hover:opacity-75">
                        <img src="https://placehold.co/150x150/eeeeee/cccccc?text=Vue+4" alt="Vue supplémentaire 3" class="w-full h-auto object-cover rounded-md border border-custom cursor-pointer hover:opacity-75">
                    </div>
                </div>

                <div class="flex flex-col">
                    <h1 class="text-2xl lg:text-3xl font-bold text-main mb-2"><?php echo htmlspecialchars($product['nom']); ?></h1>
                    
                    <?php if (!empty($product['categorie'])): ?>
                        <p class="text-xs text-muted mb-3">Catégorie : <span class="font-semibold brand-accent"><?php echo htmlspecialchars($product['categorie']); ?></span></p>
                    <?php endif; ?>

                    <div class="text-3xl font-bold brand-accent mb-5" x-text="`Prix : ${totalPrice.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}`">
                    </div>

                    <div class="prose prose-sm sm:prose text-muted max-w-none mb-5 text-sm leading-relaxed">
                        <h2 class="text-lg font-semibold text-main mb-1">Description</h2>
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    
                    <?php if($product['stock'] > 0 && $product['stock'] <= 10): ?>
                        <p class="text-sm text-yellow-600 dark:text-yellow-400 mb-4"><i class="fas fa-exclamation-triangle mr-1"></i> Plus que <?php echo $product['stock']; ?> en stock !</p>
                    <?php elseif ($product['stock'] == 0 && strtolower($product['categorie']) !== 'service' && strtolower($product['nom']) !== 'service'): ?>
                         <p class="text-sm text-red-600 dark:text-red-400 mb-4"><i class="fas fa-times-circle mr-1"></i> Actuellement en rupture de stock.</p>
                    <?php endif; ?>

                    <?php if (!empty($product_options_grouped)): ?>
                    <form id="productOptionsForm" class="mb-6 space-y-4">
                        <h3 class="text-md font-semibold text-main mb-2">Options de Personnalisation :</h3>
                        <template x-for="(group, groupIndex) in optionsConfig" :key="groupIndex">
                            <div class="border-t border-custom pt-3">
                                <label :for="'option_group_' + groupIndex" class="block text-sm font-medium text-main mb-1" x-text="group.nomGroupe + ':'"></label>
                                <template x-if="group.type === 'dropdown' && group.valeurs && group.valeurs.length > 0">
                                    <select 
                                        :name="'options[' + group.nomGroupe + ']'" 
                                        :id="'option_group_' + groupIndex"
                                        class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm bg-primary text-main"
                                        x-model="selectedOptions[groupIndex].selectedValueId"
                                        @change="updatePrice()">
                                        <template x-for="valeurOpt in group.valeurs" :key="valeurOpt.id">
                                            <option :value="valeurOpt.id.toString()" 
                                                    :data-supplement="valeurOpt.supplement"
                                                    x-text="valeurOpt.valeur + (valeurOpt.supplement > 0 ? ' (+' + parseFloat(valeurOpt.supplement).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }) + ')' : '')">
                                            </option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="group.type === 'text'">
                                    <div>
                                        <input 
                                            type="text" 
                                            :name="'options[' + group.nomGroupe + ']'" 
                                            :id="'option_group_' + groupIndex"
                                            class="w-full px-3 py-2 border border-custom rounded-md shadow-sm focus:ring-brand-green focus:border-brand-green sm:text-sm bg-primary text-main"
                                            :placeholder="group.valeurs && group.valeurs.length > 0 ? group.valeurs[0].valeur : 'Votre texte ici'"
                                            x-model="selectedOptions[groupIndex].customText"
                                            @input="updatePrice()">
                                        <p x-show="group.aide" class="text-xs text-muted mt-1" x-text="group.aide"></p>
                                    </div>
                                </template>
                                </div>
                        </template>
                    </form>
                    <?php else: ?>
                         <p class="text-muted text-sm mb-6">Ce produit n'a pas d'options de personnalisation spécifiques listées. Pour des demandes sur mesure, contactez-nous via la page <a href="index.php#devis" @click.prevent="navigate('devis'); document.title = 'Demande de Devis - CréaMod3D';" class="text-brand-green hover:underline">Devis</a>.</p>
                    <?php endif; ?>


                    <div class="mt-auto pt-4"> 
                        <button type="button" 
                                class="w-full btn-primary text-base font-semibold py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition duration-300 flex items-center justify-center 
                                       <?php echo ($product['stock'] == 0 && strtolower($product['categorie']) !== 'service' && strtolower($product['nom']) !== 'service') ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                <?php echo ($product['stock'] == 0 && strtolower($product['categorie']) !== 'service' && strtolower($product['nom']) !== 'service') ? 'disabled' : ''; ?>
                                @click="addToCart(<?php echo $product['id']; ?>)">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Ajouter au Panier
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($product_id === null && isset($_GET['id'])): ?>
        <div class="text-center py-20">
            <i class="fas fa-search fa-3x text-brand-green mb-4"></i>
            <h1 class="text-3xl font-bold text-main mb-4">Produit Non Trouvé</h1>
            <p class="text-muted mb-6">Désolé, le produit que vous recherchez n'existe pas ou n'est plus disponible.</p>
            <a href="index.php#produits" @click.prevent="navigate('produits'); document.title = 'Nos Produits - CréaMod3D';" class="btn-primary py-2 px-6 rounded-md font-semibold">
                Retourner à la boutique
            </a>
        </div>
    <?php else: ?>
         <div class="text-center py-20">
            <i class="fas fa-exclamation-triangle fa-3x text-red-500 mb-4"></i>
            <h1 class="text-3xl font-bold text-main mb-4">ID de Produit Manquant ou Invalide</h1>
            <p class="text-muted mb-6">Veuillez spécifier un ID de produit valide pour voir ses détails.</p>
            <a href="index.php#produits" @click.prevent="navigate('produits'); document.title = 'Nos Produits - CréaMod3D';" class="btn-primary py-2 px-6 rounded-md font-semibold">
                Explorer nos produits
            </a>
        </div>
    <?php endif; ?>
</main>

<?php
include 'includes/footer.php'; 
?>

<script>
// Alpine.js data handler pour la page produit
function productOptionsHandler(config) {
    return {
        basePrice: parseFloat(config.basePrice) || 0,
        optionsConfig: config.optionsConfig || [], 
        selectedOptions: [], 
        totalPrice: 0,

        initAlpine() { 
            this.totalPrice = this.basePrice;
            this.selectedOptions = this.optionsConfig.map(group => {
                let initialSelectedValueId = null;
                let initialCustomText = '';
                if (group.type === 'dropdown' && group.valeurs && group.valeurs.length > 0) {
                    initialSelectedValueId = group.valeurs[0].id.toString();
                }
                return {
                    nomGroupe: group.nomGroupe, 
                    type: group.type,
                    selectedValueId: initialSelectedValueId, 
                    customText: initialCustomText, 
                };
            });
            this.updatePrice(); 
        },

        updatePrice() {
            let currentTotalPrice = this.basePrice;
            this.selectedOptions.forEach((selection, index) => {
                const optionGroup = this.optionsConfig[index]; 
                if (!optionGroup) return;

                if (selection.type === 'dropdown' && selection.selectedValueId) {
                    const selectedValObj = optionGroup.valeurs.find(v => v.id.toString() === selection.selectedValueId);
                    if (selectedValObj) {
                        currentTotalPrice += parseFloat(selectedValObj.supplement);
                    }
                } else if (selection.type === 'text' && selection.customText.trim() !== '') {
                    if (optionGroup.valeurs && optionGroup.valeurs.length > 0) {
                         currentTotalPrice += parseFloat(optionGroup.valeurs[0].supplement);
                    }
                }
            });
            this.totalPrice = currentTotalPrice;
        },

        addToCart(productId) {
            let cartPayloadOptions = [];
            this.selectedOptions.forEach((selection, index) => {
                const optionGroup = this.optionsConfig[index];
                if (!optionGroup) return;

                if (selection.type === 'dropdown' && selection.selectedValueId) {
                    const selectedValObj = optionGroup.valeurs.find(v => v.id.toString() === selection.selectedValueId);
                    if (selectedValObj) {
                        cartPayloadOptions.push({
                            groupe: optionGroup.nomGroupe,
                            valeur_id: selectedValObj.id,
                            valeur_choisie: selectedValObj.valeur,
                            supplement: selectedValObj.supplement
                        });
                    }
                } else if (selection.type === 'text' && selection.customText.trim() !== '') {
                     const textOptionConfig = optionGroup.valeurs && optionGroup.valeurs.length > 0 ? optionGroup.valeurs[0] : { id: null, supplement: 0 };
                     cartPayloadOptions.push({
                        groupe: optionGroup.nomGroupe,
                        valeur_id: textOptionConfig.id, 
                        valeur_choisie: selection.customText.trim(),
                        supplement: textOptionConfig.supplement
                    });
                }
            });

            console.log('--- Ajout au Panier (Simulation) ---');
            console.log('Produit ID:', productId);
            console.log('Prix de base:', this.basePrice);
            console.log('Options sélectionnées pour le panier:', JSON.stringify(cartPayloadOptions, null, 2));
            console.log('Prix total calculé:', this.totalPrice);
            
            alert(`Produit ID ${productId} avec options (détails en console) serait ajouté au panier.\nPrix total: ${this.totalPrice.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}.\n\nFonctionnalité d'ajout au panier réelle à implémenter.`);
        }
    }
}
</script>
