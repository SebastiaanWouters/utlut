<?php

namespace App\Enums;

enum TtsVoice: string
{
    case Alloy = 'alloy';
    case Ash = 'ash';
    case Ballad = 'ballad';
    case Coral = 'coral';
    case Echo = 'echo';
    case Fable = 'fable';
    case Nova = 'nova';
    case Onyx = 'onyx';
    case Sage = 'sage';
    case Shimmer = 'shimmer';
    case Verse = 'verse';

    /**
     * Get a human-friendly label for the voice.
     */
    public function label(): string
    {
        return match ($this) {
            self::Alloy => 'Alloy (Neutral)',
            self::Ash => 'Ash (Warm)',
            self::Ballad => 'Ballad (Expressive)',
            self::Coral => 'Coral (Friendly)',
            self::Echo => 'Echo (Clear)',
            self::Fable => 'Fable (Storytelling)',
            self::Nova => 'Nova (Bright)',
            self::Onyx => 'Onyx (Deep)',
            self::Sage => 'Sage (Calm)',
            self::Shimmer => 'Shimmer (Gentle)',
            self::Verse => 'Verse (Dynamic)',
        };
    }

    /**
     * Get a description of the voice characteristics.
     */
    public function description(): string
    {
        return match ($this) {
            self::Alloy => 'A balanced, neutral voice suitable for general content',
            self::Ash => 'A warm and approachable voice with a friendly tone',
            self::Ballad => 'An expressive voice ideal for emotional content',
            self::Coral => 'A friendly and conversational voice',
            self::Echo => 'A clear and articulate voice for professional content',
            self::Fable => 'A storytelling voice with character and depth',
            self::Nova => 'A bright and energetic voice',
            self::Onyx => 'A deep and resonant voice',
            self::Sage => 'A calm and measured voice for relaxed listening',
            self::Shimmer => 'A gentle and soothing voice',
            self::Verse => 'A dynamic and engaging voice with varied intonation',
        };
    }

    /**
     * Get all voices as options for a select input.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $voice) {
            $options[$voice->value] = $voice->label();
        }

        return $options;
    }
}
