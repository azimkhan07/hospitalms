<?php

namespace Database\Seeders;

use App\Models\Icd10Code;
use Illuminate\Database\Seeder;

/**
 * A global baseline of common ICD-10 codes (tenant_id NULL). Idempotent:
 * updateOrCreate keyed on the code, so re-seeding never duplicates a row and
 * never touches a facility's own additions.
 */
class Icd10Seeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->codes() as $row) {
            Icd10Code::updateOrCreate(
                ['code' => $row[0]],
                [
                    'tenant_id' => null,
                    'description' => $row[1],
                    'chapter' => $row[2],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * [code, description, chapter]
     *
     * @return array<int, array{0:string,1:string,2:string}>
     */
    private function codes(): array
    {
        return [
            ['A09', 'Diarrhoea and gastroenteritis of presumed infectious origin', 'Certain infectious and parasitic diseases'],
            ['A01.0', 'Typhoid fever', 'Certain infectious and parasitic diseases'],
            ['A15.0', 'Tuberculosis of lung', 'Certain infectious and parasitic diseases'],
            ['A46', 'Erysipelas', 'Certain infectious and parasitic diseases'],
            ['A49.9', 'Bacterial infection, unspecified', 'Certain infectious and parasitic diseases'],
            ['A90', 'Dengue fever', 'Certain infectious and parasitic diseases'],
            ['B34.9', 'Viral infection, unspecified', 'Certain infectious and parasitic diseases'],
            ['B50.9', 'Plasmodium falciparum malaria, unspecified', 'Certain infectious and parasitic diseases'],
            ['B19.9', 'Unspecified viral hepatitis', 'Certain infectious and parasitic diseases'],
            ['B35.9', 'Dermatophytosis, unspecified', 'Certain infectious and parasitic diseases'],
            ['C50.9', 'Malignant neoplasm of breast, unspecified', 'Neoplasms'],
            ['C34.9', 'Malignant neoplasm of bronchus or lung, unspecified', 'Neoplasms'],
            ['D50.9', 'Iron deficiency anaemia, unspecified', 'Diseases of the blood and immune mechanism'],
            ['D64.9', 'Anaemia, unspecified', 'Diseases of the blood and immune mechanism'],
            ['E03.9', 'Hypothyroidism, unspecified', 'Endocrine, nutritional and metabolic diseases'],
            ['E05.9', 'Thyrotoxicosis, unspecified', 'Endocrine, nutritional and metabolic diseases'],
            ['E11', 'Type 2 diabetes mellitus', 'Endocrine, nutritional and metabolic diseases'],
            ['E10', 'Type 1 diabetes mellitus', 'Endocrine, nutritional and metabolic diseases'],
            ['E66.9', 'Obesity, unspecified', 'Endocrine, nutritional and metabolic diseases'],
            ['E78.5', 'Hyperlipidaemia, unspecified', 'Endocrine, nutritional and metabolic diseases'],
            ['E86', 'Volume depletion (dehydration)', 'Endocrine, nutritional and metabolic diseases'],
            ['F32.9', 'Depressive episode, unspecified', 'Mental and behavioural disorders'],
            ['F41.2', 'Mixed anxiety and depressive disorder', 'Mental and behavioural disorders'],
            ['F41.9', 'Anxiety disorder, unspecified', 'Mental and behavioural disorders'],
            ['F10.2', 'Mental and behavioural disorders due to use of alcohol', 'Mental and behavioural disorders'],
            ['G43.9', 'Migraine, unspecified', 'Diseases of the nervous system'],
            ['G40.9', 'Epilepsy, unspecified', 'Diseases of the nervous system'],
            ['G44.2', 'Tension-type headache', 'Diseases of the nervous system'],
            ['G20', 'Parkinson disease', 'Diseases of the nervous system'],
            ['G62.9', 'Polyneuropathy, unspecified', 'Diseases of the nervous system'],
            ['H10.9', 'Conjunctivitis, unspecified', 'Diseases of the eye and adnexa'],
            ['H25.9', 'Senile cataract, unspecified', 'Diseases of the eye and adnexa'],
            ['H40.9', 'Glaucoma, unspecified', 'Diseases of the eye and adnexa'],
            ['H66.9', 'Otitis media, unspecified', 'Diseases of the ear and mastoid process'],
            ['H83.3', 'Noise effects on inner ear', 'Diseases of the ear and mastoid process'],
            ['I10', 'Essential (primary) hypertension', 'Diseases of the circulatory system'],
            ['I20.9', 'Angina pectoris, unspecified', 'Diseases of the circulatory system'],
            ['I25.9', 'Chronic ischaemic heart disease, unspecified', 'Diseases of the circulatory system'],
            ['I21.9', 'Acute myocardial infarction, unspecified', 'Diseases of the circulatory system'],
            ['I50.9', 'Heart failure, unspecified', 'Diseases of the circulatory system'],
            ['I64', 'Stroke, not specified as haemorrhage or infarction', 'Diseases of the circulatory system'],
            ['I83.9', 'Varicose veins of lower extremity', 'Diseases of the circulatory system'],
            ['I84.9', 'Unspecified haemorrhoids', 'Diseases of the circulatory system'],
            ['J02.9', 'Acute pharyngitis, unspecified', 'Diseases of the respiratory system'],
            ['J06.9', 'Acute upper respiratory infection, unspecified', 'Diseases of the respiratory system'],
            ['J18.9', 'Pneumonia, unspecified', 'Diseases of the respiratory system'],
            ['J20.9', 'Acute bronchitis, unspecified', 'Diseases of the respiratory system'],
            ['J30.9', 'Allergic rhinitis, unspecified', 'Diseases of the respiratory system'],
            ['J45.9', 'Asthma, unspecified', 'Diseases of the respiratory system'],
            ['J44.9', 'Chronic obstructive pulmonary disease, unspecified', 'Diseases of the respiratory system'],
            ['K21.9', 'Gastro-oesophageal reflux disease', 'Diseases of the digestive system'],
            ['K29.7', 'Gastritis, unspecified', 'Diseases of the digestive system'],
            ['K27.9', 'Peptic ulcer, site unspecified', 'Diseases of the digestive system'],
            ['K59.0', 'Constipation', 'Diseases of the digestive system'],
            ['K35.9', 'Acute appendicitis, unspecified', 'Diseases of the digestive system'],
            ['K40.9', 'Unilateral inguinal hernia', 'Diseases of the digestive system'],
            ['K80.9', 'Cholelithiasis', 'Diseases of the digestive system'],
            ['K92.2', 'Gastrointestinal haemorrhage, unspecified', 'Diseases of the digestive system'],
            ['L02.9', 'Cutaneous abscess, furuncle and carbuncle, unspecified', 'Diseases of the skin and subcutaneous tissue'],
            ['L20.9', 'Atopic dermatitis, unspecified', 'Diseases of the skin and subcutaneous tissue'],
            ['L30.9', 'Dermatitis, unspecified', 'Diseases of the skin and subcutaneous tissue'],
            ['L40.9', 'Psoriasis, unspecified', 'Diseases of the skin and subcutaneous tissue'],
            ['L50.9', 'Urticaria, unspecified', 'Diseases of the skin and subcutaneous tissue'],
            ['L03.9', 'Cellulitis, unspecified', 'Diseases of the skin and subcutaneous tissue'],
            ['M13.9', 'Arthritis, unspecified', 'Diseases of the musculoskeletal system'],
            ['M17.9', 'Osteoarthritis of knee, unspecified', 'Diseases of the musculoskeletal system'],
            ['M54.5', 'Low back pain', 'Diseases of the musculoskeletal system'],
            ['M75.1', 'Rotator cuff syndrome', 'Diseases of the musculoskeletal system'],
            ['M79.1', 'Myalgia', 'Diseases of the musculoskeletal system'],
            ['M06.9', 'Rheumatoid arthritis, unspecified', 'Diseases of the musculoskeletal system'],
            ['N30.9', 'Cystitis, unspecified', 'Diseases of the genitourinary system'],
            ['N39.0', 'Urinary tract infection, site not specified', 'Diseases of the genitourinary system'],
            ['N18.9', 'Chronic kidney disease, unspecified', 'Diseases of the genitourinary system'],
            ['N20.0', 'Calculus of kidney', 'Diseases of the genitourinary system'],
            ['N40', 'Enlarged prostate (benign prostatic hyperplasia)', 'Diseases of the genitourinary system'],
            ['N80.9', 'Endometriosis, unspecified', 'Diseases of the genitourinary system'],
            ['O80', 'Encounter for full-term uncomplicated delivery', 'Pregnancy, childbirth and the puerperium'],
            ['O26.9', 'Pregnancy-related condition, unspecified', 'Pregnancy, childbirth and the puerperium'],
            ['O20.9', 'Haemorrhage in early pregnancy, unspecified', 'Pregnancy, childbirth and the puerperium'],
            ['Z34.9', 'Supervision of normal pregnancy, unspecified', 'Factors influencing health status'],
            ['P07.3', 'Preterm infant, unspecified', 'Certain conditions originating in the perinatal period'],
            ['R05', 'Cough', 'Symptoms and signs not elsewhere classified'],
            ['R06.2', 'Wheezing', 'Symptoms and signs not elsewhere classified'],
            ['R10.4', 'Other and unspecified abdominal pain', 'Symptoms and signs not elsewhere classified'],
            ['R11', 'Nausea and vomiting', 'Symptoms and signs not elsewhere classified'],
            ['R42', 'Dizziness and giddiness', 'Symptoms and signs not elsewhere classified'],
            ['R51', 'Headache', 'Symptoms and signs not elsewhere classified'],
            ['R50.9', 'Fever, unspecified', 'Symptoms and signs not elsewhere classified'],
            ['R07.9', 'Chest pain, unspecified', 'Symptoms and signs not elsewhere classified'],
            ['R53', 'Malaise and fatigue', 'Symptoms and signs not elsewhere classified'],
            ['S61.9', 'Open wound of wrist and hand, unspecified', 'Injury, poisoning and external causes'],
            ['S01.9', 'Open wound of head, unspecified', 'Injury, poisoning and external causes'],
            ['T14.0', 'Superficial injury of unspecified body region', 'Injury, poisoning and external causes'],
            ['T78.4', 'Allergy, unspecified', 'Injury, poisoning and external causes'],
            ['S52.5', 'Fracture of lower end of radius', 'Injury, poisoning and external causes'],
            ['T39.9', 'Poisoning by nonopioid analgesic, unspecified', 'Injury, poisoning and external causes'],
            ['U07.1', 'COVID-19, virus identified', 'Codes for special purposes'],
            ['Z00.0', 'General adult medical examination', 'Factors influencing health status'],
            ['Z23', 'Encounter for immunization', 'Factors influencing health status'],
            ['Z76.0', 'Encounter for issue of repeat prescription', 'Factors influencing health status'],
        ];
    }
}
