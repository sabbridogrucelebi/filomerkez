import React, { useEffect, useRef } from 'react';
import { View, StyleSheet, Animated, Dimensions } from 'react-native';
import { Video, ResizeMode } from 'expo-av';

const { width, height } = Dimensions.get('window');

export default function GlobalSplashScreen({ onFinish }) {
    const bgOpacity = useRef(new Animated.Value(1)).current;
    const videoRef = useRef(null);

    const handleVideoEnd = (status) => {
        if (status.didJustFinish) {
            // Video bittikten sonra biraz bekle (yazı okunsun) sonra kapat
            setTimeout(() => {
                Animated.timing(bgOpacity, {
                    toValue: 0,
                    duration: 400,
                    useNativeDriver: true,
                }).start(() => {
                    if (onFinish) onFinish();
                });
            }, 3000);
        }
    };

    // Fallback: video 8 saniyede hâlâ bitmezse zorla kapat
    useEffect(() => {
        const timeout = setTimeout(() => {
            Animated.timing(bgOpacity, {
                toValue: 0,
                duration: 400,
                useNativeDriver: true,
            }).start(() => {
                if (onFinish) onFinish();
            });
        }, 15000);
        return () => clearTimeout(timeout);
    }, []);

    return (
        <Animated.View style={[styles.container, { opacity: bgOpacity }]}>
            <Video
                ref={videoRef}
                source={require('../../assets/video.mp4')}
                style={styles.video}
                resizeMode={ResizeMode.COVER}
                shouldPlay
                isLooping={false}
                isMuted={true}
                onPlaybackStatusUpdate={handleVideoEnd}
            />
        </Animated.View>
    );
}

const styles = StyleSheet.create({
    container: {
        ...StyleSheet.absoluteFillObject,
        zIndex: 9999,
        backgroundColor: '#000000',
    },
    video: {
        width: width,
        height: height,
    },
});
